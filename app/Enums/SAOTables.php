<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

use Modules\Core\Enums\Concerns\HasModuleTablesUtils;

enum SAOTables: string
{
    use HasModuleTablesUtils;

    case Projects = 'sao_projects';
    case TicketStatuses = 'sao_ticket_statuses';
    case WorkflowSchemes = 'sao_workflow_schemes';
    case WorkflowTransitions = 'sao_workflow_transitions';
    case TicketTypes = 'sao_ticket_types';
    case ProjectTicketTypes = 'sao_project_ticket_types';
    case Tickets = 'sao_tickets';
    case TicketComments = 'sao_ticket_comments';
    case Labels = 'sao_labels';
    case TicketLabel = 'sao_ticket_label';
    case Connections = 'sao_connections';
    case ProjectBindings = 'sao_project_bindings';
    case TicketLinks = 'sao_ticket_links';
    case SyncOperations = 'sao_sync_operations';
}
