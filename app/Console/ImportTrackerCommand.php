<?php

declare(strict_types=1);

namespace Modules\SAO\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\ImportScope;
use Modules\SAO\Enums\SyncDirection;
use Modules\SAO\Jobs\ImportTrackerHistoryJob;
use Modules\SAO\Models\ProjectBinding;
use Modules\SAO\Services\BindingCutoverService;
use Modules\SAO\Services\TrackerImportService;

/**
 * Imports an external tracker's issue history into SAO — the "switch to Laraplate,
 * import your history" migration — for each `issues` binding of a connection.
 * Idempotent, so it is safe to re-run. `--scope=open` skips terminal issues;
 * `--cutover` flips the binding to authoritative afterwards; `--queue` runs each
 * binding's import in the background instead of inline.
 */
final class ImportTrackerCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sao:tracker:import
        {connection : The connection name to import from}
        {--project= : Restrict to one project by name}
        {--scope=all : all | open (open skips issues whose remote status maps to a terminal category)}
        {--cutover : After importing, make SAO authoritative by flipping the binding sync direction}
        {--cutover-direction=disabled : disabled | outbound — the direction cutover flips the binding to}
        {--queue : Dispatch each binding import as a background job instead of running inline}';

    /**
     * @var string
     */
    protected $description = 'Import an external tracker history into SAO (optionally open-only, with cutover)';

    public function handle(TrackerImportService $importer, BindingCutoverService $cutoverService): int
    {
        $scope = ImportScope::tryFrom((string) $this->option('scope'));

        if (! $scope instanceof ImportScope) {
            $this->error('The --scope option must be one of: ' . implode(', ', ImportScope::values()) . '.');

            return self::FAILURE;
        }

        $cutover = null;

        if ($this->option('cutover')) {
            $cutover = SyncDirection::tryFrom((string) $this->option('cutover-direction'));

            if (! in_array($cutover, [SyncDirection::Disabled, SyncDirection::Outbound], true)) {
                $this->error('The --cutover-direction option must be one of: disabled, outbound.');

                return self::FAILURE;
            }
        }

        $name = (string) $this->argument('connection');
        $query = ProjectBinding::query()
            ->with(['remoteConnection', 'project'])
            ->where('capability', Capability::Issues)
            ->whereHas('remoteConnection', static fn (Builder $connection): Builder => $connection->where('name', $name));

        $project = $this->option('project');

        if (is_string($project) && $project !== '') {
            $query->whereHas('project', static fn (Builder $builder): Builder => $builder->where('name', $project));
        }

        $bindings = $query->get();

        if ($bindings->isEmpty()) {
            $this->warn("No issues binding for a connection named [{$name}].");

            return self::SUCCESS;
        }

        if ($this->option('queue')) {
            foreach ($bindings as $binding) {
                ImportTrackerHistoryJob::dispatch($binding, $scope, $cutover);
            }

            $this->info("Dispatched {$bindings->count()} import job(s).");

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($bindings as $binding) {
            $report = $importer->import($binding, $scope);

            if (! $report->processed) {
                continue;
            }

            if ($cutover !== null) {
                $cutoverService->cutover($binding, $cutover);
            }

            $rows[] = [
                (string) ($binding->project->name ?? $binding->project_id),
                (string) $report->created,
                (string) $report->updated,
                (string) $report->filtered,
                (string) $report->skipped,
                $report->truncated ? 'yes' : 'no',
                $cutover?->value ?? '—',
            ];
        }

        if ($rows === []) {
            $this->warn('No issues bindings to import.');

            return self::SUCCESS;
        }

        $this->table(['Project', 'Created', 'Updated', 'Filtered', 'Skipped', 'Truncated', 'Cutover'], $rows);

        return self::SUCCESS;
    }
}
