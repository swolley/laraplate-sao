<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Models\Project;

/**
 * Allocates the next ticket number for a project.
 *
 * Gaps are accepted: a rolled-back transaction loses its number. Making the
 * sequence gapless would require serializing every creation, which no serious
 * tracker does.
 */
final class TicketKeyAllocator
{
    /**
     * @return array{number: int, key: string}
     */
    public function allocate(Project $project): array
    {
        return $project->getConnection()->transaction(function () use ($project): array {
            /** @var Project $locked */
            $locked = $project->newQuery()
                ->whereKey($project->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $number = $locked->next_ticket_number + 1;

            // A direct update rather than save(): next_ticket_number is guarded,
            // and this is the only code allowed to move it.
            $locked->newQuery()
                ->whereKey($locked->getKey())
                ->update(['next_ticket_number' => $number]);

            $project->setAttribute('next_ticket_number', $number);
            $project->syncOriginalAttribute('next_ticket_number');

            return [
                'number' => $number,
                'key' => $locked->key_prefix . '-' . $number,
            ];
        });
    }
}
