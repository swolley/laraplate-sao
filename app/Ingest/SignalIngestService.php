<?php

declare(strict_types=1);

namespace Modules\SAO\Ingest;

use Modules\SAO\Enums\SignalState;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Signal;
use Modules\SAO\Models\SignalOccurrence;

/**
 * Turns a received error into a signal: it resolves the group key, opens the
 * project's signal for that key or recurs the existing one, and records an
 * occurrence. The group key is comparable across projects, so the same error in
 * two projects yields two signals (one per project) that share a key.
 */
final readonly class SignalIngestService
{
    public function __construct(private GroupKeyResolver $groupKeyResolver) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(Project $project, array $payload): Signal
    {
        $groupKey = $this->groupKeyResolver->resolve($payload);

        $signal = Signal::query()->firstOrNew([
            'project_id' => $project->getKey(),
            'group_key' => $groupKey,
        ]);

        if (! $signal->exists) {
            $signal->algo_version = is_numeric($payload['algo_version'] ?? null) ? (int) $payload['algo_version'] : 1;
            $signal->state = SignalState::Open;
            $signal->occurrence_count = 0;
            $signal->first_seen_at = now();
        } elseif ($signal->state === SignalState::Resolved) {
            // The error came back: a resolved signal reopens on recurrence.
            $signal->state = SignalState::Open;
        }

        $signal->occurrence_count++;
        $signal->last_seen_at = now();
        $signal->save();

        SignalOccurrence::query()->create([
            'signal_id' => $signal->getKey(),
            'environment' => is_string($payload['environment'] ?? null) ? $payload['environment'] : null,
            'context' => is_array($payload['context'] ?? null) ? $payload['context'] : null,
            'occurred_at' => now(),
        ]);

        return $signal;
    }
}
