<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\ImportRunStatus;
use Modules\SAO\Enums\ImportScope;
use Modules\SAO\Enums\SyncDirection;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\ImportRun;
use Modules\SAO\Models\ProjectBinding;
use Modules\SAO\Models\TicketLink;
use Modules\SAO\Services\TrackerImportService;
use Modules\SAO\Tests\Support\Drivers\PagingIssuesDriver;
use Modules\SAO\Tests\Support\Drivers\RecordingIssuesDriver;

uses(RefreshDatabase::class);

/**
 * Registers a seeded issues driver and returns a bound issues binding.
 *
 * @param  array<string, string>  $seed  remoteId => remoteStatus
 * @param  array<string, string>  $statusMap
 */
function importBinding(array $seed, array $statusMap): ProjectBinding
{
    app(DriverRegistry::class)->register(new RecordingIssuesDriver($seed));
    [$project, $type] = sync_fixture();

    return sync_binding(SyncDirection::Inbound, $project, $type, $statusMap);
}

/**
 * Registers a paging (offset-cursor) issues driver and returns a binding bound to
 * it, so resume-across-invocations can be exercised page by page.
 */
function pagingBinding(PagingIssuesDriver $driver): ProjectBinding
{
    app(DriverRegistry::class)->register($driver);
    [$project, $type] = sync_fixture();

    $connection = Connection::factory()->create([
        'driver_key' => 'paging',
        'capabilities' => [Capability::Issues],
        'credential' => ['token' => 'x'],
    ]);

    return ProjectBinding::factory()->create([
        'project_id' => $project->id,
        'connection_id' => $connection->id,
        'capability' => Capability::Issues,
        'remote_identifier' => 'proj',
        'sync_direction' => SyncDirection::Inbound,
        'status_map' => [],
        'config' => ['ticket_type' => $type->id],
    ]);
}

test('scope all imports the whole history and is idempotent', function (): void {
    $binding = importBinding(['1' => 'Done', '2' => 'Open', '3' => 'Todo'], ['Done' => 'closed', 'Open' => 'open']);

    $first = app(TrackerImportService::class)->import($binding, ImportScope::All);

    expect($first->created)->toBe(3)
        ->and($first->filtered)->toBe(0)
        ->and(TicketLink::query()->where('connection_id', $binding->connection_id)->count())->toBe(3);

    $second = app(TrackerImportService::class)->import($binding, ImportScope::All);

    expect($second->created)->toBe(0)
        ->and($second->updated)->toBe(3)
        ->and(TicketLink::query()->where('connection_id', $binding->connection_id)->count())->toBe(3);
});

test('scope open skips issues whose remote status maps to a terminal category', function (): void {
    $binding = importBinding(['1' => 'Done', '2' => 'Open', '3' => 'Rejected'], [
        'Done' => 'closed',
        'Open' => 'open',
        'Rejected' => 'rejected',
    ]);

    $report = app(TrackerImportService::class)->import($binding, ImportScope::Open);

    expect($report->created)->toBe(1)   // only the Open issue
        ->and($report->filtered)->toBe(2);
});

test('an unmapped remote status is imported as open (open scope keeps it)', function (): void {
    $binding = importBinding(['1' => 'WeirdStatus'], ['Done' => 'closed']);

    $report = app(TrackerImportService::class)->import($binding, ImportScope::Open);

    expect($report->created)->toBe(1)
        ->and($report->filtered)->toBe(0);
});

test('a full import walks every page and completes the run', function (): void {
    $driver = new PagingIssuesDriver(['1' => 'Open', '2' => 'Open', '3' => 'Open', '4' => 'Open', '5' => 'Open'], pageSize: 2);
    $binding = pagingBinding($driver);

    $report = app(TrackerImportService::class)->import($binding, ImportScope::All);

    expect($report->created)->toBe(5)
        ->and($report->pages)->toBe(3)
        ->and($report->truncated)->toBeFalse()
        ->and($driver->listedCursors)->toBe([null, '2', '4']);

    $run = ImportRun::query()->where('binding_id', $binding->id)->sole();

    expect($run->status)->toBe(ImportRunStatus::Completed)
        ->and($run->cursor)->toBeNull()
        ->and($run->created_count)->toBe(5)
        ->and($run->pages)->toBe(3);
});

test('an interrupted run resumes from its stored cursor instead of page one', function (): void {
    $driver = new PagingIssuesDriver(['1' => 'Open', '2' => 'Open', '3' => 'Open', '4' => 'Open', '5' => 'Open'], pageSize: 2);
    $binding = pagingBinding($driver);

    // Simulate a crash after the first two pages: two tickets already imported,
    // the run left Running with the cursor pointing at the third page (offset 4).
    ImportRun::query()->create([
        'binding_id' => $binding->id,
        'scope' => ImportScope::All,
        'status' => ImportRunStatus::Running,
        'cursor' => '4',
        'created_count' => 4,
        'pages' => 2,
    ]);
    TicketLink::query()->create(['ticket_id' => Modules\SAO\Models\Ticket::factory()->forProject($binding->project)->create()->id, 'connection_id' => $binding->connection_id, 'remote_id' => '1']);
    TicketLink::query()->create(['ticket_id' => Modules\SAO\Models\Ticket::factory()->forProject($binding->project)->create()->id, 'connection_id' => $binding->connection_id, 'remote_id' => '2']);

    $report = app(TrackerImportService::class)->import($binding, ImportScope::All);

    expect($driver->listedCursors)->toBe(['4'])          // resumed straight at page three
        ->and($report->created)->toBe(5)                 // 4 prior + the one new page's one item...
        ->and($report->pages)->toBe(3);

    $run = ImportRun::query()->where('binding_id', $binding->id)->sole();

    expect($run->status)->toBe(ImportRunStatus::Completed)
        ->and($run->cursor)->toBeNull();
});

test('re-importing a completed migration opens a fresh run', function (): void {
    $driver = new PagingIssuesDriver(['1' => 'Open', '2' => 'Open'], pageSize: 2);
    $binding = pagingBinding($driver);

    app(TrackerImportService::class)->import($binding, ImportScope::All);
    app(TrackerImportService::class)->import($binding, ImportScope::All);

    $runs = ImportRun::query()->where('binding_id', $binding->id)->get();

    expect($runs)->toHaveCount(2)
        ->and($runs->every(fn (ImportRun $run): bool => $run->status === ImportRunStatus::Completed))->toBeTrue();
});
