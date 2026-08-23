<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Data\TrackerImportReport;
use Modules\SAO\Drivers\Contracts\IssuesCapability;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Drivers\Support\ConnectionCredentialResolver;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\ImportScope;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Enums\SyncOutcome;
use Modules\SAO\Models\ProjectBinding;

/**
 * Imports an external tracker's issue history into SAO — the "switch to Laraplate,
 * bring your history" migration. It walks every page of the `issues` driver's list
 * and upserts each issue through {@see IssueSyncService::import()} (idempotent by
 * `TicketLink`, so re-running is safe and effectively resumable), independent of
 * the binding's ongoing sync direction — a migration is a deliberate one-time pull.
 *
 * With {@see ImportScope::Open} it skips issues whose remote status maps to a
 * terminal category through the binding's `status_map`; an unmapped status is kept
 * as open so nothing still active is dropped. Comment/attachment history and a
 * stored resume cursor are follow-ups; idempotency already makes a restart safe.
 */
final readonly class TrackerImportService
{
    private const int MAX_PAGES = 5000;

    public function __construct(
        private DriverRegistry $registry,
        private ConnectionCredentialResolver $resolver,
        private IssueSyncService $sync,
    ) {}

    public function import(ProjectBinding $binding, ImportScope $scope): TrackerImportReport
    {
        if ($binding->capability !== Capability::Issues) {
            return TrackerImportReport::skipped();
        }

        $driver = $this->registry->get($binding->remoteConnection->driver_key);

        if (! $driver instanceof IssuesCapability) {
            return TrackerImportReport::skipped();
        }

        $context = $binding->bindingContext($this->resolver);

        $created = 0;
        $updated = 0;
        $filtered = 0;
        $skipped = 0;
        $pages = 0;
        $truncated = false;
        $cursor = null;

        do {
            if ($pages >= self::MAX_PAGES) {
                $truncated = true;

                break;
            }

            $page = $driver->list($context, $cursor);
            $pages++;

            foreach ($page->items as $issue) {
                if ($scope === ImportScope::Open && $this->isTerminal($binding, $issue)) {
                    $filtered++;

                    continue;
                }

                match ($this->sync->import($binding, $issue)) {
                    SyncOutcome::Created => $created++,
                    SyncOutcome::Updated => $updated++,
                    default => $skipped++,
                };
            }

            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        return new TrackerImportReport(
            processed: true,
            created: $created,
            updated: $updated,
            filtered: $filtered,
            skipped: $skipped,
            pages: $pages,
            truncated: $truncated,
        );
    }

    /**
     * Whether the issue's remote status maps to a terminal canonical category. A
     * missing or unmapped status is treated as non-terminal (open), so open-only
     * never drops a still-active issue.
     *
     * @param  array<string, mixed>  $issue
     */
    private function isTerminal(ProjectBinding $binding, array $issue): bool
    {
        $remoteStatus = $issue['remote_status'] ?? null;

        if (! is_string($remoteStatus)) {
            return false;
        }

        $category = StatusCategory::tryFrom((string) (($binding->status_map ?? [])[$remoteStatus] ?? ''));

        return $category?->isTerminal() ?? false;
    }
}
