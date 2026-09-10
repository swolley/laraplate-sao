<?php

declare(strict_types=1);

namespace Modules\SAO\Console;

use Illuminate\Console\Command;
use Modules\SAO\Services\RetentionService;

/**
 * Hard-deletes aged, high-volume SAO data so the store does not grow without
 * bound (see {@see RetentionService}). Windows come from `sao.retention.*`.
 * `--dry-run` reports what would be removed without deleting anything. Scheduled
 * registration is gated by `sao.retention.enabled`; the command always runs.
 */
final class PruneCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sao:prune {--dry-run : Report what would be removed without deleting}';

    /**
     * @var string
     */
    protected $description = 'Prune aged occurrences, ingest events, deployments and closed-project data <fg=bright-red>(🎫 Modules\SAO)</fg=bright-red>';

    public function handle(RetentionService $retention): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = [
            ['Signal occurrences', $retention->pruneSignalOccurrences((int) config('sao.retention.signal_occurrences_days', 90), $dryRun)],
            ['Ingest events', $retention->pruneIngestEvents((int) config('sao.retention.ingest_events_days', 30), $dryRun)],
            ['Deployments', $retention->pruneDeployments((int) config('sao.retention.deployments_days', 180), $dryRun)],
            ['Closed-project data', $retention->pruneClosedProjects((int) config('sao.retention.closed_project_days', 30), $dryRun)],
        ];

        $this->table(['Target', $dryRun ? 'Would remove' : 'Removed'], array_map(
            static fn (array $row): array => [$row[0], (string) $row[1]],
            $rows,
        ));

        $total = array_sum(array_column($rows, 1));
        $this->info($dryRun ? "Dry run: {$total} row(s) would be removed." : "Pruned {$total} row(s).");

        return self::SUCCESS;
    }
}
