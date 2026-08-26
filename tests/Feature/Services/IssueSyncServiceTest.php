<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Enums\SyncDirection;
use Modules\SAO\Enums\SyncOutcome;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketLink;
use Modules\SAO\Services\IssueSyncService;
use Modules\SAO\Tests\Support\Drivers\RecordingIssuesDriver;

uses(RefreshDatabase::class);

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

test('import brings in an unmapped-status issue that reconcile would skip', function (): void {
    [$project, $type] = sync_fixture();
    $binding = sync_binding(SyncDirection::Inbound, $project, $type, statusMap: ['Done' => 'closed']);
    $issue = ['remote_id' => 'R42', 'title' => 'Legacy', 'remote_status' => 'Weird'];

    // reconcile gates an unmapped remote status; a migration import does not.
    expect(app(IssueSyncService::class)->reconcile($binding, $issue))->toBe(SyncOutcome::UnmappedStatus)
        ->and(TicketLink::query()->where('remote_id', 'R42')->exists())->toBeFalse();

    expect(app(IssueSyncService::class)->import($binding, $issue))->toBe(SyncOutcome::Created)
        ->and(TicketLink::query()->where('remote_id', 'R42')->exists())->toBeTrue();
});
