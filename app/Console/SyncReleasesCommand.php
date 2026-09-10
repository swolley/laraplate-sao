<?php

declare(strict_types=1);

namespace Modules\SAO\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\SAO\Attribution\ReleaseSyncService;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Models\ProjectBinding;

/**
 * Reconciles `releases` bindings' tags into the release table, promoting an
 * announced release to shipped once its stable tag is cut (see
 * {@see ReleaseSyncService}). Run after a release, or on a schedule, so a release
 * first known from a candidate is updated when it truly ships. Idempotent; safe
 * with nothing configured.
 */
final class SyncReleasesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sao:releases:sync {connection? : The connection name to sync; omit to sync them all}';

    /**
     * @var string
     */
    protected $description = 'Sync release tags and promote announced releases whose stable tag now exists <fg=bright-red>(🎫 Modules\SAO)</fg=bright-red>';

    public function handle(ReleaseSyncService $service): int
    {
        $query = ProjectBinding::query()
            ->with(['remoteConnection', 'project'])
            ->where('capability', Capability::Releases);

        $name = $this->argument('connection');

        if (is_string($name) && $name !== '') {
            $query->whereHas('remoteConnection', static fn (Builder $connection): Builder => $connection->where('name', $name));
        }

        $bindings = $query->get();

        if ($bindings->isEmpty()) {
            $this->warn(is_string($name) && $name !== ''
                ? "No releases binding for a connection named [{$name}]."
                : 'No releases bindings configured.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($bindings as $binding) {
            $report = $service->sync($binding);

            if (! $report->processed) {
                continue;
            }

            $rows[] = [
                (string) ($binding->remoteConnection->name ?? $binding->connection_id),
                (string) ($binding->project->name ?? $binding->project_id),
                (string) $report->tagsScanned,
                (string) $report->tagsRegistered,
                (string) $report->releasesPromoted,
                $report->truncated ? 'yes' : 'no',
            ];
        }

        if ($rows === []) {
            $this->warn('No releases bindings to sync.');

            return self::SUCCESS;
        }

        $this->table(['Connection', 'Project', 'Tags', 'Registered', 'Promoted', 'Truncated'], $rows);

        return self::SUCCESS;
    }
}
