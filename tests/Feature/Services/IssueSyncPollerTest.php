<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
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
use Modules\SAO\Services\IssueSyncPoller;
use Modules\SAO\Tests\Support\Drivers\PaginatingIssuesDriver;
use Modules\SAO\Tests\Support\Drivers\RecordingIssuesDriver;

uses(RefreshDatabase::class);

/**
 * @return array{0: Project, 1: TicketType}
 */
function poll_fixture(): array
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

function poll_binding(string $driverKey, SyncDirection $direction, Project $project, TicketType $type): ProjectBinding
{
    $connection = Connection::factory()->create([
        'driver_key' => $driverKey,
        'capabilities' => [Capability::Issues],
        'credential' => ['token' => 'x'],
    ]);

    return ProjectBinding::factory()->create([
        'project_id' => $project->id,
        'connection_id' => $connection->id,
        'capability' => Capability::Issues,
        'remote_identifier' => 'proj',
        'sync_direction' => $direction,
        'status_map' => [],
        'config' => ['ticket_type' => $type->id],
    ]);
}

test('polling pages the driver to completion and creates a ticket per remote issue', function (): void {
    $driver = new PaginatingIssuesDriver(total: 3);
    app(DriverRegistry::class)->register($driver);

    [$project, $type] = poll_fixture();
    $binding = poll_binding('paginating', SyncDirection::Inbound, $project, $type);

    $report = app(IssueSyncPoller::class)->poll($binding);

    expect($report->processed)->toBeTrue()
        ->and($report->pages)->toBe(3)
        ->and($driver->pageReads)->toBe(3)
        ->and($report->count(SyncOutcome::Created))->toBe(3)
        ->and($report->truncated)->toBeFalse()
        ->and(Ticket::query()->where('project_id', $project->id)->count())->toBe(3)
        ->and(TicketLink::query()->count())->toBe(3);
});

test('a second poll updates the linked tickets rather than duplicating them', function (): void {
    app(DriverRegistry::class)->register(new PaginatingIssuesDriver(total: 2));

    [$project, $type] = poll_fixture();
    $binding = poll_binding('paginating', SyncDirection::Inbound, $project, $type);
    $poller = app(IssueSyncPoller::class);

    $poller->poll($binding);
    $report = $poller->poll($binding);

    expect($report->count(SyncOutcome::Updated))->toBe(2)
        ->and($report->count(SyncOutcome::Created))->toBe(0)
        ->and(Ticket::query()->where('project_id', $project->id)->count())->toBe(2)
        ->and(TicketLink::query()->count())->toBe(2);
});

test('a non-inbound binding is reported as unprocessed and pulls nothing', function (): void {
    app(DriverRegistry::class)->register(new RecordingIssuesDriver(['r-1' => 'open']));

    [$project, $type] = poll_fixture();
    $binding = poll_binding('recording', SyncDirection::Outbound, $project, $type);

    $report = app(IssueSyncPoller::class)->poll($binding);

    expect($report->processed)->toBeFalse()
        ->and($report->total())->toBe(0)
        ->and(Ticket::query()->count())->toBe(0);
});

test('the sao:sync:issues command polls inbound bindings', function (): void {
    app(DriverRegistry::class)->register(new PaginatingIssuesDriver(total: 2));

    [$project, $type] = poll_fixture();
    poll_binding('paginating', SyncDirection::Inbound, $project, $type);

    $this->artisan('sao:sync:issues')->assertSuccessful();

    expect(Ticket::query()->where('project_id', $project->id)->count())->toBe(2);
});

test('the inbound poll is scheduled when sync is enabled', function (): void {
    $commands = collect(app(Schedule::class)->events())
        ->map(static fn ($event): string => (string) $event->command);

    expect($commands->contains(static fn (string $command): bool => str_contains($command, 'sao:sync:issues')))->toBeTrue();
});
