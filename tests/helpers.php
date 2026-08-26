<?php

declare(strict_types=1);

use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\ChangeRefType;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Enums\SyncDirection;
use Modules\SAO\Enums\TicketReleaseState;
use Modules\SAO\Models\ChangeRef;
use Modules\SAO\Models\ClosurePolicy;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\Environment;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\ProjectBinding;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketRelease;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;
use Modules\SAO\Models\WorkflowTransition;

/**
 * @return array{0: Project, 1: TicketType}
 */
function sync_fixture(): array
{
    $open = TicketStatus::factory()->category(StatusCategory::Open)->create(['name' => 'Open']);
    $scheme = WorkflowScheme::factory()->create(['name' => 'Simple']);
    WorkflowTransition::factory()->for($scheme, 'scheme')->create([
        'from_status_id' => null,
        'to_status_id' => $open->id,
        'label' => 'Open',
    ]);
    $type = TicketType::factory()->create(['workflow_scheme_id' => $scheme->id]);
    $project = Project::factory()->create(['key_prefix' => 'SAO']);
    $project->ticketTypes()->attach($type->id, ['is_default' => true]);

    return [$project, $type];
}

/**
 * @param  array<string, string>  $statusMap
 */
function sync_binding(SyncDirection $direction, Project $project, TicketType $type, array $statusMap = []): ProjectBinding
{
    $connection = Connection::factory()->create([
        'driver_key' => 'recording',
        'capabilities' => [Capability::Issues],
        'credential' => ['token' => 'x'],
    ]);

    return ProjectBinding::factory()->create([
        'project_id' => $project->id,
        'connection_id' => $connection->id,
        'capability' => Capability::Issues,
        'remote_identifier' => 'proj',
        'sync_direction' => $direction,
        'status_map' => $statusMap,
        'config' => ['ticket_type' => $type->id],
    ]);
}

/**
 * A ticket ready to close: Open → Done(closed) scheme, a merged fixing PR, a
 * shipped release deployed on production, and an active close policy whose
 * deploy-based conditions hold.
 *
 * @return array{ticket: Ticket, done: TicketStatus}
 */
function coord_closure_fixture(): array
{
    $open = TicketStatus::factory()->category(StatusCategory::Open)->create(['name' => 'Open']);
    $done = TicketStatus::factory()->category(StatusCategory::Closed)->create(['name' => 'Done']);

    $scheme = WorkflowScheme::factory()->create(['name' => 'Closable']);
    WorkflowTransition::factory()->for($scheme, 'scheme')->create(['from_status_id' => null, 'to_status_id' => $open->id, 'label' => 'Open']);
    WorkflowTransition::factory()->for($scheme, 'scheme')->create(['from_status_id' => $open->id, 'to_status_id' => $done->id, 'label' => 'Close']);

    $type = TicketType::factory()->create(['workflow_scheme_id' => $scheme->id]);
    $project = Project::factory()->create();
    $project->ticketTypes()->attach($type->id, ['is_default' => true]);

    $ticket = Ticket::factory()->forProject($project)->create(['ticket_type_id' => $type->id, 'ticket_status_id' => $open->id]);

    ChangeRef::factory()->create(['ticket_id' => $ticket->id, 'type' => ChangeRefType::PullRequest, 'identifier' => '10', 'merged_at' => now()]);
    $release = Release::factory()->for($project)->shipped()->create(['version' => '1.0.0']);
    TicketRelease::factory()->create(['ticket_id' => $ticket->id, 'release_id' => $release->id, 'state' => TicketReleaseState::Shipped]);
    Environment::factory()->for($project)->create(['name' => 'production', 'current_version' => '1.0.0']);

    ClosurePolicy::factory()->for($project)->closes()->create([
        'conditions' => [['key' => 'pull_request_merged'], ['key' => 'fix_deployed_there']],
    ]);

    return ['ticket' => $ticket, 'done' => $done];
}
