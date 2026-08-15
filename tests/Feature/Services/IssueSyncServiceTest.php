<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Enums\SyncDirection;
use Modules\SAO\Enums\SyncOutcome;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\ProjectBinding;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketLink;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;
use Modules\SAO\Models\WorkflowTransition;
use Modules\SAO\Services\IssueSyncService;
use Modules\SAO\Tests\Support\Drivers\RecordingIssuesDriver;

uses(RefreshDatabase::class);

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

beforeEach(function (): void {
    $this->driver = new RecordingIssuesDriver;
    app(DriverRegistry::class)->register($this->driver);
});

test('an outbound push creates the remote issue once and is idempotent on retry', function (): void {
    [$project, $type] = sync_fixture();
    $binding = sync_binding(SyncDirection::Outbound, $project, $type);
    $ticket = Ticket::factory()->forProject($project)->create(['title' => 'Bug']);

    $service = app(IssueSyncService::class);

    expect($service->push($binding, $ticket))->toBe(SyncOutcome::Created)
        ->and($service->push($binding, $ticket))->toBe(SyncOutcome::SkippedIdempotent)
        ->and($this->driver->createCount)->toBe(1)
        ->and($this->driver->updateCount)->toBe(0)
        ->and(TicketLink::query()->where('ticket_id', $ticket->id)->count())->toBe(1);
});

test('a disabled binding pushes nothing', function (): void {
    [$project, $type] = sync_fixture();
    $binding = sync_binding(SyncDirection::Disabled, $project, $type);
    $ticket = Ticket::factory()->forProject($project)->create();

    expect(app(IssueSyncService::class)->push($binding, $ticket))->toBe(SyncOutcome::SkippedDirection)
        ->and($this->driver->createCount)->toBe(0);
});

test('an inbound-only binding refuses an outbound push', function (): void {
    [$project, $type] = sync_fixture();
    $binding = sync_binding(SyncDirection::Inbound, $project, $type);
    $ticket = Ticket::factory()->forProject($project)->create();

    expect(app(IssueSyncService::class)->push($binding, $ticket))->toBe(SyncOutcome::SkippedDirection);
});

test('an inbound pull creates a SAO ticket then updates it on the next pull', function (): void {
    [$project, $type] = sync_fixture();
    $binding = sync_binding(SyncDirection::Inbound, $project, $type, ['Done' => 'resolved']);
    $this->driver->remote['R1'] = ['remote_id' => 'R1', 'title' => 'Remote bug', 'remote_status' => 'Done'];

    $service = app(IssueSyncService::class);

    expect($service->pull($binding, 'R1'))->toBe(SyncOutcome::Created)
        ->and(TicketLink::query()->where('remote_id', 'R1')->count())->toBe(1);

    expect($service->pull($binding, 'R1'))->toBe(SyncOutcome::Updated)
        ->and(TicketLink::query()->where('remote_id', 'R1')->count())->toBe(1);
});

test('an unmapped remote status stops the pull without creating a ticket', function (): void {
    [$project, $type] = sync_fixture();
    $binding = sync_binding(SyncDirection::Inbound, $project, $type, statusMap: []);
    $this->driver->remote['R9'] = ['remote_id' => 'R9', 'title' => 'x', 'remote_status' => 'Done'];

    expect(app(IssueSyncService::class)->pull($binding, 'R9'))->toBe(SyncOutcome::UnmappedStatus)
        ->and(TicketLink::query()->where('remote_id', 'R9')->exists())->toBeFalse();
});

test('an outbound-only binding refuses an inbound pull', function (): void {
    [$project, $type] = sync_fixture();
    $binding = sync_binding(SyncDirection::Outbound, $project, $type);
    $this->driver->remote['R1'] = ['remote_id' => 'R1', 'title' => 'x', 'remote_status' => 'Done'];

    expect(app(IssueSyncService::class)->pull($binding, 'R1'))->toBe(SyncOutcome::SkippedDirection);
});
