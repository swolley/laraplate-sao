<?php

declare(strict_types=1);

namespace Modules\SAO\Authorization;

use Modules\Core\Authorization\Contracts\DeclaresPermissions;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\IngestEvent;
use Modules\SAO\Models\OwnershipSuggestion;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;
use Override;

/**
 * SAO domain permissions.
 *
 * Operations beyond CRUD are the ones the domain actually distinguishes:
 * assigning a ticket, moving it through its workflow, overriding a workflow that
 * would otherwise deadlock the work, closing a ticket by applying a closure
 * policy, accepting an ownership suggestion, probing a connection's health, and
 * replaying a stored ingest event. The last four back the SPA-facing domain
 * actions in {@see \Modules\SAO\Services\DomainActions\SaoDomainActionRegistrar}.
 *
 * `view`/`create` are SAO's own read and write anchors, distinct from Core's
 * generic `select`/`insert`: {@see \Modules\SAO\Services\TicketQueryService}
 * resolves ACLs against `view`.
 */
final class SAOPermissions implements DeclaresPermissions
{
    #[Override]
    public static function operations(): array
    {
        return [
            Ticket::class => ['view', 'create', 'update', 'delete', 'assign', 'transition', 'transition_override', 'close'],
            Project::class => ['view', 'create', 'update', 'delete'],
            TicketStatus::class => ['view', 'create', 'update', 'delete'],
            TicketType::class => ['view', 'create', 'update', 'delete'],
            WorkflowScheme::class => ['view', 'create', 'update', 'delete'],
            OwnershipSuggestion::class => ['accept'],
            Connection::class => ['health'],
            IngestEvent::class => ['replay'],
        ];
    }

    #[Override]
    public static function excludedModels(): array
    {
        return [];
    }
}
