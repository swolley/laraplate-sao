<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Data\SyncReport;
use Modules\SAO\Drivers\Contracts\IssuesCapability;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Drivers\Support\ConnectionCredentialResolver;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Models\ProjectBinding;

/**
 * Pulls a whole `issues` binding: it pages the driver's issue list to
 * completion and reconciles every issue into SAO through {@see IssueSyncService}.
 *
 * This is the outbound-poll counterpart to the inbound webhook transport — it is
 * how SAO stays current with trackers that offer no push. Each page follows the
 * driver's `nextCursor` until it runs dry; a page cap guards against a driver
 * that never terminates, and hitting it is reported (never a silent stop). A
 * binding that does not sync inbound, or whose driver is not an issues driver,
 * is reported as unprocessed rather than touched.
 */
final readonly class IssueSyncPoller
{
    private const int MAX_PAGES = 1000;

    public function __construct(
        private DriverRegistry $registry,
        private ConnectionCredentialResolver $resolver,
        private IssueSyncService $sync,
    ) {}

    public function poll(ProjectBinding $binding): SyncReport
    {
        if ($binding->capability !== Capability::Issues || ! $binding->sync_direction->syncsInbound()) {
            return SyncReport::skipped();
        }

        $driver = $this->registry->get($binding->remoteConnection->driver_key);

        if (! $driver instanceof IssuesCapability) {
            return SyncReport::skipped();
        }

        $context = $binding->bindingContext($this->resolver);

        $outcomes = [];
        $pages = 0;
        $cursor = null;
        $truncated = false;

        do {
            if ($pages >= self::MAX_PAGES) {
                $truncated = true;

                break;
            }

            $page = $driver->list($context, $cursor);
            $pages++;

            foreach ($page->items as $issue) {
                $outcome = $this->sync->reconcile($binding, $issue);
                $outcomes[$outcome->value] = ($outcomes[$outcome->value] ?? 0) + 1;
            }

            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        return new SyncReport($outcomes, $pages, processed: true, truncated: $truncated);
    }
}
