<?php

declare(strict_types=1);

namespace Modules\SAO\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\SAO\Attribution\VcsScanService;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Models\ProjectBinding;

/**
 * Scans `vcs` bindings' commits for ticket references, recording fix/mention
 * change refs and attributing fixing commits to their releases through
 * {@see VcsScanService}. The pull path for code-to-work attribution; idempotent
 * and safe to re-run, so it also backfills history. Safe with nothing configured
 * (no vcs binding simply means no work).
 */
final class ScanCommitsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sao:vcs:scan
        {connection? : The connection name to scan; omit to scan them all}
        {--range=main : The branch or commit range to walk}';

    /**
     * @var string
     */
    protected $description = 'Scan vcs bindings for ticket references and attribute fixes to releases';

    public function handle(VcsScanService $service): int
    {
        $query = ProjectBinding::query()
            ->with(['remoteConnection', 'project'])
            ->where('capability', Capability::Vcs);

        $name = $this->argument('connection');

        if (is_string($name) && $name !== '') {
            $query->whereHas('remoteConnection', static fn (Builder $connection): Builder => $connection->where('name', $name));
        }

        $bindings = $query->get();

        if ($bindings->isEmpty()) {
            $this->warn(is_string($name) && $name !== ''
                ? "No vcs binding for a connection named [{$name}]."
                : 'No vcs bindings configured.');

            return self::SUCCESS;
        }

        $range = (string) $this->option('range');
        $rows = [];

        foreach ($bindings as $binding) {
            $report = $service->scan($binding, $range);

            if (! $report->processed) {
                continue;
            }

            $rows[] = [
                (string) ($binding->remoteConnection->name ?? $binding->connection_id),
                (string) ($binding->project->name ?? $binding->project_id),
                (string) $report->commitsScanned,
                (string) $report->fixLinks,
                (string) $report->mentionLinks,
                (string) $report->releasesAttributed,
                $report->truncated ? 'yes' : 'no',
            ];
        }

        if ($rows === []) {
            $this->warn('No vcs bindings to scan.');

            return self::SUCCESS;
        }

        $this->table(['Connection', 'Project', 'Commits', 'Fixes', 'Mentions', 'Releases', 'Truncated'], $rows);

        return self::SUCCESS;
    }
}
