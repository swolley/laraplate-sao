<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Enums\ImportScope;
use Modules\SAO\Enums\SyncDirection;
use Modules\SAO\Models\TicketLink;
use Modules\SAO\Services\TrackerImportService;
use Modules\SAO\Tests\Support\Drivers\RecordingIssuesDriver;

uses(RefreshDatabase::class);

/**
 * Registers a seeded issues driver and returns a bound issues binding.
 *
 * @param  array<string, string>  $seed  remoteId => remoteStatus
 * @param  array<string, string>  $statusMap
 */
function importBinding(array $seed, array $statusMap): \Modules\SAO\Models\ProjectBinding
{
    app(DriverRegistry::class)->register(new RecordingIssuesDriver($seed));
    [$project, $type] = sync_fixture();

    return sync_binding(SyncDirection::Inbound, $project, $type, $statusMap);
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
