<?php

declare(strict_types=1);

namespace Modules\SAO\Console;

use Illuminate\Console\Command;
use Modules\SAO\Drivers\Support\DeployEvent;
use Modules\SAO\Enums\DeploymentStatus;
use Modules\SAO\Ingest\DeploymentIngestService;
use Modules\SAO\Models\Project;

/**
 * Records a deployment without any external integration — the integration-free
 * path for CLI-only CD steps and for replay in tests. Idempotent when an
 * `--external-id` is given: re-running with the same id advances the same
 * deployment instead of creating a second.
 */
final class RecordDeploymentCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sao:deploy:record
        {project : The project name to record the deployment against}
        {version : The deployed version label}
        {--env= : Target environment name, e.g. production}
        {--status=succeeded : One of started|succeeded|failed|rolled_back|superseded}
        {--external-id= : Idempotency key for re-runs of the same deploy}';

    /**
     * @var string
     */
    protected $description = 'Record a deployment for a project (integration-free CD / replay) <fg=bright-red>(🎫 Modules\SAO)</fg=bright-red>';

    public function handle(DeploymentIngestService $ingest): int
    {
        $name = (string) $this->argument('project');
        $project = Project::query()->where('name', $name)->first();

        if (! $project instanceof Project) {
            $this->error("Project [{$name}] not found.");

            return self::FAILURE;
        }

        $version = (string) $this->argument('version');

        if ($version === '') {
            $this->error('A version label is required.');

            return self::FAILURE;
        }

        $status = DeploymentStatus::tryFrom((string) $this->option('status'));

        if (! $status instanceof DeploymentStatus) {
            $this->error('The --status option must be one of: ' . implode(', ', DeploymentStatus::values()) . '.');

            return self::FAILURE;
        }

        $environment = $this->option('env');
        $externalId = $this->option('external-id');

        $deployment = $ingest->ingest($project, new DeployEvent(
            version: $version,
            status: $status,
            environmentName: is_string($environment) && $environment !== '' ? $environment : null,
            externalId: is_string($externalId) && $externalId !== '' ? $externalId : null,
        ));

        $this->info(sprintf(
            'Recorded deployment #%d — %s %s → %s (%s).',
            $deployment->getKey(),
            $project->name,
            $deployment->version,
            $deployment->environment?->name ?? 'no environment',
            $deployment->status->value,
        ));

        return self::SUCCESS;
    }
}
