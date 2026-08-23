<?php

declare(strict_types=1);

namespace Modules\SAO\Attribution;

use Modules\SAO\Drivers\Contracts\ReleasesCapability;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Drivers\Support\ConnectionCredentialResolver;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Models\ProjectBinding;

/**
 * Reconciles a `releases` binding's tags against the known releases, so an
 * announced release derived from a candidate (RC) is **promoted to shipped when
 * its stable tag is actually cut** — deterministically, rather than depending on
 * which tag `firstTagContaining` happens to return during a commit scan.
 *
 * It lists every tag, and for each tag that maps (by normalized version) to a
 * release attribution already created, registers the tag and promotes the release
 * on a stable tag. It never creates releases for tags with no attribution — that
 * keeps the release table to versions SAO actually correlates to work. Idempotent
 * and page-bounded like the issue poller.
 */
final readonly class ReleaseSyncService
{
    private const int MAX_PAGES = 1000;

    public function __construct(
        private DriverRegistry $registry,
        private ConnectionCredentialResolver $resolver,
        private ReleaseRegistrar $registrar,
    ) {}

    public function sync(ProjectBinding $binding): ReleaseSyncReport
    {
        if ($binding->capability !== Capability::Releases) {
            return ReleaseSyncReport::skipped();
        }

        $driver = $this->registry->get($binding->remoteConnection->driver_key);

        if (! $driver instanceof ReleasesCapability) {
            return ReleaseSyncReport::skipped();
        }

        $context = $binding->bindingContext($this->resolver);

        $tagsScanned = 0;
        $tagsRegistered = 0;
        $releasesPromoted = 0;
        $pages = 0;
        $truncated = false;
        $cursor = null;

        do {
            if ($pages >= self::MAX_PAGES) {
                $truncated = true;

                break;
            }

            $page = $driver->tags($context, $cursor);
            $pages++;

            foreach ($page->items as $item) {
                $tag = (string) ($item['tag'] ?? '');

                if ($tag === '') {
                    continue;
                }

                $tagsScanned++;

                $registration = $this->registrar->register($binding->project_id, $tag, createIfMissing: false);

                if ($registration === null) {
                    continue;
                }

                $tagsRegistered++;

                if ($registration->promoted) {
                    $releasesPromoted++;
                }
            }

            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        return new ReleaseSyncReport(
            processed: true,
            tagsScanned: $tagsScanned,
            tagsRegistered: $tagsRegistered,
            releasesPromoted: $releasesPromoted,
            pages: $pages,
            truncated: $truncated,
        );
    }
}
