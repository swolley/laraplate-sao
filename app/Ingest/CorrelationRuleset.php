<?php

declare(strict_types=1);

namespace Modules\SAO\Ingest;

use Modules\SAO\Models\Project;

/**
 * The ordered, inspectable ruleset that attaches an ingested event to a project.
 * Every correlation records which rule won (or that none did), so a misroute is
 * diagnosable rather than silent (§14). The built-in rule maps a canonical
 * `project_key` to a `Project.key_prefix`; more rules can be prepended without
 * changing callers.
 */
final class CorrelationRuleset
{
    /**
     * @param  array<string, mixed>  $canonical
     * @return array{project: ?Project, rule: ?string}
     */
    public function correlate(array $canonical): array
    {
        $projectKey = $canonical['project_key'] ?? null;

        if (is_string($projectKey) && $projectKey !== '') {
            $project = Project::query()->where('key_prefix', $projectKey)->first();

            if ($project instanceof Project) {
                return ['project' => $project, 'rule' => 'project_key'];
            }
        }

        return ['project' => null, 'rule' => null];
    }
}
