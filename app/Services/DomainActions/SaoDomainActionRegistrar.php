<?php

declare(strict_types=1);

namespace Modules\SAO\Services\DomainActions;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\User;
use Modules\Core\Services\Crud\DomainActionRegistry;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Ingest\IngestReplayService;
use Modules\SAO\Models\ClosureAudit;
use Modules\SAO\Models\ClosurePolicy;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\IngestEvent;
use Modules\SAO\Models\OwnershipSuggestion;
use Modules\SAO\Models\SourceProfile;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Services\ClosureApplicationService;
use Modules\SAO\Services\ConnectionHealthService;
use Modules\SAO\Services\OwnershipSuggestionApplier;
use Modules\SAO\Services\WorkflowService;

/**
 * Maps SAO domain actions onto the services that implement them.
 *
 * Handlers stay thin: state guards and permissions live in
 * {@see \Modules\SAO\Policies\SaoModelPolicy} and the services, exactly as they
 * do for the Filament pages that drive the same code. Each handler is the
 * sanctioned single path to its operation — a status change goes through
 * {@see WorkflowService}, never a raw `ticket_status_id` write.
 */
final class SaoDomainActionRegistrar
{
    public function register(DomainActionRegistry $registry): void
    {
        $registry->register(Ticket::class, 'transition', static fn (Model $record, array $payload, User $user): Ticket => resolve(WorkflowService::class)->transition(
            $record,
            TicketStatus::query()->findOrFail($payload['to_status_id']),
            ChangeContext::forUser($user),
        ));

        $registry->register(Ticket::class, 'close', static fn (Model $record, array $payload, User $user): ?ClosureAudit => resolve(ClosureApplicationService::class)->apply(
            $record,
            ClosurePolicy::query()->findOrFail($payload['policy_id']),
            isset($payload['reporting_environment']) ? (string) $payload['reporting_environment'] : null,
        ));

        $registry->register(OwnershipSuggestion::class, 'accept', static fn (Model $record, array $payload, User $user): Ticket => resolve(OwnershipSuggestionApplier::class)->apply($record));

        $registry->register(Connection::class, 'health', static function (Model $record, array $payload, User $user): array {
            $result = resolve(ConnectionHealthService::class)->check($record);

            return [
                'healthy' => $result->healthy,
                'detail' => $result->detail,
                'health_state' => $record->health_state->value,
                'last_checked_at' => $record->last_checked_at?->toIso8601String(),
            ];
        });

        $registry->register(IngestEvent::class, 'replay', static function (Model $record, array $payload, User $user): array {
            $profile_id = $payload['profile_id'] ?? $record->source_profile_id;

            return resolve(IngestReplayService::class)->dryRun($record, SourceProfile::query()->findOrFail($profile_id));
        });
    }
}
