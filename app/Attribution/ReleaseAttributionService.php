<?php

declare(strict_types=1);

namespace Modules\SAO\Attribution;

use Modules\SAO\Drivers\Contracts\ReleasesCapability;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Enums\ReleaseStatus;
use Modules\SAO\Enums\TicketReleaseState;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketRelease;

/**
 * Maps a fixing commit to the release that carries it. It asks the `releases`
 * driver for the first tag containing the commit, classifies that tag (stable vs
 * candidate), and upserts the {@see Release}, {@see ReleaseTag} and
 * {@see TicketRelease} — so "which version fixes this ticket" is answered from
 * data, feeding `FixStatusResolver` and closure.
 *
 * The release version is always the normalized stable label (the semver core,
 * `v1.4.0-rc.1` → `1.4.0`), so a candidate (RC) records the future stable version
 * it will become: the same `Release` row, still `announced` and realized only by a
 * `candidate` tag, until a stable tag of that version ships it. Both the RC and its
 * eventual stable tag therefore attach to one release.
 *
 * Idempotent and monotonic: a stable tag ships the release (status + a best-effort
 * `released_at`) and the ticket attribution (`shipped`), and neither is ever
 * walked back by a later candidate tag. `released_at` is a best-effort stamp; the
 * deploy ingest sharpens actual timing.
 */
final readonly class ReleaseAttributionService
{
    public function __construct(private ReleaseRegistrar $registrar) {}

    public function attribute(Ticket $ticket, string $commitSha, ReleasesCapability $driver, BindingContext $context): ?TicketRelease
    {
        $tag = $driver->firstTagContaining($context, $commitSha);

        if ($tag === null || $tag === '') {
            return null;
        }

        $registration = $this->registrar->register($ticket->project_id, $tag, createIfMissing: true);

        if ($registration === null) {
            return null;
        }

        $isStable = $registration->release->status === ReleaseStatus::Shipped;

        return $this->upsertTicketRelease($ticket, $registration->release, $isStable);
    }

    private function upsertTicketRelease(Ticket $ticket, Release $release, bool $isStable): TicketRelease
    {
        $ticketRelease = TicketRelease::query()->firstOrNew([
            'ticket_id' => $ticket->getKey(),
            'release_id' => $release->getKey(),
        ]);

        // Shipped is monotonic: a stable tag ships the attribution and a later
        // candidate never demotes it back to promised.
        if ($isStable || $ticketRelease->state === TicketReleaseState::Shipped) {
            $ticketRelease->state = TicketReleaseState::Shipped;
        } else {
            $ticketRelease->state ??= TicketReleaseState::Promised;
        }

        $ticketRelease->save();

        return $ticketRelease;
    }
}
