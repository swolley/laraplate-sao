<?php

declare(strict_types=1);

namespace Modules\SAO\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\SyncOutcome;
use Modules\SAO\Models\ProjectBinding;
use Modules\SAO\Services\IssueSyncPoller;

/**
 * Polls `issues` bindings that sync inbound and reconciles each remote issue
 * into SAO through {@see IssueSyncPoller}. This is the scheduled pull path for
 * trackers with no push transport; it is safe to run with nothing configured
 * (no inbound binding simply means no work).
 */
final class PullIssuesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sao:sync:issues {connection? : The connection name to poll; omit to poll them all}';

    /**
     * @var string
     */
    protected $description = 'Pull inbound issue bindings from their external trackers';

    public function handle(IssueSyncPoller $poller): int
    {
        $query = ProjectBinding::query()
            ->with(['remoteConnection', 'project'])
            ->where('capability', Capability::Issues);

        $name = $this->argument('connection');

        if (is_string($name) && $name !== '') {
            $query->whereHas('remoteConnection', static fn (Builder $connection): Builder => $connection->where('name', $name));
        }

        $bindings = $query->get();

        if ($bindings->isEmpty()) {
            $this->warn(is_string($name) && $name !== ''
                ? "No issues binding for a connection named [{$name}]."
                : 'No issues bindings configured.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($bindings as $binding) {
            $report = $poller->poll($binding);

            if (! $report->processed) {
                continue;
            }

            $rows[] = [
                (string) ($binding->remoteConnection->name ?? $binding->connection_id),
                (string) ($binding->project->name ?? $binding->project_id),
                (string) $report->count(SyncOutcome::Created),
                (string) $report->count(SyncOutcome::Updated),
                (string) ($report->count(SyncOutcome::UnmappedStatus) + $report->count(SyncOutcome::NotFound)),
                $report->truncated ? 'yes' : 'no',
            ];
        }

        if ($rows === []) {
            $this->warn('No inbound issues bindings to poll.');

            return self::SUCCESS;
        }

        $this->table(['Connection', 'Project', 'Created', 'Updated', 'Skipped', 'Truncated'], $rows);

        return self::SUCCESS;
    }
}
