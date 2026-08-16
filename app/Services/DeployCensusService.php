<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Illuminate\Support\Collection;
use Modules\SAO\Data\EnvironmentCensus;
use Modules\SAO\Models\Environment;
use Modules\SAO\Models\Project;

/**
 * Keeps and reports what version each environment is running. Two feeds write
 * the same fields: `observe()` for a passive signal (a deployment event that
 * reached us) and `recordProbe()` for an active check (we asked). Both stamp
 * `last_seen_at`, and `census()` reads it back with an honest staleness flag so
 * the answer to "what runs where" never pretends to be fresher than it is.
 */
final class DeployCensusService
{
    /**
     * Passively record a version we were told about, without asserting we
     * verified it just now beyond the fact that the signal arrived.
     */
    public function observe(Environment $environment, string $version): Environment
    {
        return $this->stamp($environment, $version);
    }

    /**
     * Actively record a version we went and checked.
     */
    public function recordProbe(Environment $environment, string $version): Environment
    {
        return $this->stamp($environment, $version);
    }

    /**
     * Whether what we know about the environment is older than the TTL. An
     * environment never seen is stale by definition.
     */
    public function isStale(Environment $environment, int $ttlMinutes): bool
    {
        if ($environment->last_seen_at === null) {
            return true;
        }

        return $environment->last_seen_at->lt(now()->subMinutes($ttlMinutes));
    }

    /**
     * The project's deploy census: one row per environment with its version and
     * freshness against the given TTL.
     *
     * @return Collection<int, EnvironmentCensus>
     */
    public function census(Project $project, int $ttlMinutes): Collection
    {
        return $project->environments()
            ->orderBy('name')
            ->get()
            ->map(fn (Environment $environment): EnvironmentCensus => new EnvironmentCensus(
                environment: $environment->name,
                version: $environment->current_version,
                last_seen_at: $environment->last_seen_at,
                is_stale: $this->isStale($environment, $ttlMinutes),
            ));
    }

    private function stamp(Environment $environment, string $version): Environment
    {
        $environment->forceFill([
            'current_version' => $version,
            'last_seen_at' => now(),
        ])->save();

        return $environment;
    }
}
