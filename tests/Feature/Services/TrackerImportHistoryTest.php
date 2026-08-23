<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\CommentOrigin;
use Modules\SAO\Enums\ImportScope;
use Modules\SAO\Enums\SyncDirection;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\ProjectBinding;
use Modules\SAO\Models\TicketLink;
use Modules\SAO\Services\TrackerImportService;
use Modules\SAO\Tests\Support\Drivers\HistoryIssuesDriver;
use Modules\SAO\Tests\Support\Drivers\RecordingIssuesDriver;

uses(RefreshDatabase::class);

/**
 * Registers a history-capable issues driver and returns a binding bound to it.
 */
function historyBinding(HistoryIssuesDriver $driver): ProjectBinding
{
    app(DriverRegistry::class)->register($driver);
    [$project, $type] = sync_fixture();

    $connection = Connection::factory()->create([
        'driver_key' => 'history',
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

test('import brings each ticket its comments and attachments, idempotently', function (): void {
    Storage::fake(config('media-library.disk_name'));

    $driver = new HistoryIssuesDriver(
        seed: ['1' => 'Open'],
        comments: ['1' => [
            ['remote_id' => 'c10', 'body' => 'First remote comment'],
            ['remote_id' => 'c11', 'body' => 'Second remote comment'],
        ]],
        attachments: ['1' => [
            ['remote_id' => 'a20', 'filename' => 'trace.txt', 'contents' => 'stack trace bytes'],
        ]],
    );
    $binding = historyBinding($driver);

    $report = app(TrackerImportService::class)->import($binding, ImportScope::All);

    expect($report->created)->toBe(1)
        ->and($report->comments)->toBe(2)
        ->and($report->attachments)->toBe(1);

    $ticket = TicketLink::query()->where('connection_id', $binding->connection_id)->sole()->ticket;

    expect($ticket->comments()->count())->toBe(2)
        ->and($ticket->comments()->where('origin', CommentOrigin::System)->count())->toBe(2)
        ->and($ticket->getMedia('attachments'))->toHaveCount(1)
        ->and($ticket->getMedia('attachments')->first()->file_name)->toBe('trace.txt')
        ->and($ticket->getMedia('attachments')->first()->getCustomProperty('remote_id'))->toBe('a20');

    // Re-running imports neither a second comment nor a second attachment.
    $second = app(TrackerImportService::class)->import($binding, ImportScope::All);

    expect($second->comments)->toBe(0)
        ->and($second->attachments)->toBe(0)
        ->and($ticket->fresh()->comments()->count())->toBe(2)
        ->and($ticket->fresh()->getMedia('attachments'))->toHaveCount(1);
});

test('a driver without history capability imports no comments or attachments', function (): void {
    Storage::fake(config('media-library.disk_name'));

    app(DriverRegistry::class)->register(new RecordingIssuesDriver(['1' => 'Open']));
    [$project, $type] = sync_fixture();
    $binding = sync_binding(SyncDirection::Inbound, $project, $type, ['Open' => 'open']);

    $report = app(TrackerImportService::class)->import($binding, ImportScope::All);

    expect($report->created)->toBe(1)
        ->and($report->comments)->toBe(0)
        ->and($report->attachments)->toBe(0);
});
