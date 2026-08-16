<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Carbon\CarbonInterface;
use Modules\SAO\Data\TimeToTruth;
use Modules\SAO\Enums\ReleaseStatus;
use Modules\SAO\Models\ClosureAudit;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\Signal;
use Modules\SAO\Models\Ticket;

/**
 * Measures the lag between a signal appearing and the truths about its fix
 * becoming knowable — the metric that says how quickly the loop closes. All
 * intervals are anchored on the signal's `first_seen_at`; a signal with no
 * linked ticket, or a truth not yet established, yields null.
 */
final class TimeToTruthService
{
    public function __construct(private readonly FixStatusResolver $fixStatus) {}

    public function forSignal(Signal $signal): TimeToTruth
    {
        $anchor = $signal->first_seen_at;
        $ticket = $signal->ticket;

        if ($anchor === null || ! $ticket instanceof Ticket) {
            return new TimeToTruth(null, null, null);
        }

        return new TimeToTruth(
            time_to_fix_merged_seconds: $this->timeToFixMerged($ticket, $anchor),
            time_to_deploy_gap_known_seconds: $this->timeToDeployGapKnown($ticket, $anchor),
            time_to_premature_reopen_seconds: $this->timeToPrematureReopen($ticket, $anchor),
        );
    }

    private function timeToFixMerged(Ticket $ticket, CarbonInterface $anchor): ?int
    {
        $mergedAt = $ticket->changeRefs()->mergedPullRequests()->min('merged_at');

        return $mergedAt === null ? null : (int) $anchor->diffInSeconds($mergedAt, absolute: true);
    }

    /**
     * The deploy gap becomes knowable the moment a shipped release carrying the
     * fix exists while at least one environment still runs something else.
     */
    private function timeToDeployGapKnown(Ticket $ticket, CarbonInterface $anchor): ?int
    {
        $release = $ticket->releases()
            ->where('status', ReleaseStatus::Shipped->value)
            ->orderBy('released_at')
            ->first();

        if (! $release instanceof Release || $release->released_at === null) {
            return null;
        }

        if ($this->fixStatus->forTicket($ticket)->missing_environments === []) {
            return null;
        }

        return (int) $anchor->diffInSeconds($release->released_at, absolute: true);
    }

    private function timeToPrematureReopen(Ticket $ticket, CarbonInterface $anchor): ?int
    {
        $reopenedAt = ClosureAudit::query()
            ->where('ticket_id', $ticket->getKey())
            ->where('is_premature', true)
            ->whereNotNull('reopened_at')
            ->min('reopened_at');

        return $reopenedAt === null ? null : (int) $anchor->diffInSeconds($reopenedAt, absolute: true);
    }
}
