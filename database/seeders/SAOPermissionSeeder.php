<?php

declare(strict_types=1);

namespace Modules\SAO\Database\Seeders;

use Modules\Core\Models\Permission;
use Modules\Core\Overrides\Seeder;
use Modules\Core\Support\PermissionName;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\IngestEvent;
use Modules\SAO\Models\OwnershipSuggestion;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;

/**
 * Registers the SAO domain permissions.
 *
 * Names come from PermissionName so the `{connection}.{table}.{operation}`
 * convention lives in one place — it was previously rebuilt by hand in three,
 * which is three chances to drift.
 */
final class SAOPermissionSeeder extends Seeder
{
    /**
     * Operations beyond CRUD are the ones the domain actually distinguishes:
     * assigning a ticket, moving it through its workflow, overriding a workflow
     * that would otherwise deadlock the work, closing a ticket by applying a
     * closure policy, accepting an ownership suggestion, probing a connection's
     * health, and replaying a stored ingest event. The last four back the
     * SPA-facing domain actions in {@see \Modules\SAO\Services\DomainActions\SaoDomainActionRegistrar}.
     *
     * @var array<class-string, list<string>>
     */
    private const array OPERATIONS = [
        Ticket::class => ['view', 'create', 'update', 'delete', 'assign', 'transition', 'transition_override', 'close'],
        Project::class => ['view', 'create', 'update', 'delete'],
        TicketStatus::class => ['view', 'create', 'update', 'delete'],
        TicketType::class => ['view', 'create', 'update', 'delete'],
        WorkflowScheme::class => ['view', 'create', 'update', 'delete'],
        OwnershipSuggestion::class => ['accept'],
        Connection::class => ['health'],
        IngestEvent::class => ['replay'],
    ];

    public function run(): void
    {
        $permission_model = new Permission;

        if (! $permission_model->getConnection()->getSchemaBuilder()->hasTable($permission_model->getTable())) {
            return;
        }

        foreach (self::OPERATIONS as $model_class => $operations) {
            foreach ($operations as $operation) {
                $permission_model->newQuery()->firstOrCreate([
                    'name' => PermissionName::forClass($model_class, $operation),
                ]);
            }
        }

        $this->command?->line('    - SAO domain permissions <fg=green>updated</>');
    }
}
