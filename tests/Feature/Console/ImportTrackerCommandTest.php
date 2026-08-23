<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Enums\SyncDirection;
use Modules\SAO\Jobs\ImportTrackerHistoryJob;
use Modules\SAO\Models\TicketLink;
use Modules\SAO\Tests\Support\Drivers\RecordingIssuesDriver;

uses(RefreshDatabase::class);

test('the command imports open issues and cuts the binding over', function (): void {
    app(DriverRegistry::class)->register(new RecordingIssuesDriver(['1' => 'Open', '2' => 'Done']));
    [$project, $type] = sync_fixture();
    $binding = sync_binding(SyncDirection::Inbound, $project, $type, ['Open' => 'open', 'Done' => 'closed']);
    $connection = $binding->remoteConnection->name;

    $this->artisan('sao:tracker:import', [
        'connection' => $connection,
        '--scope' => 'open',
        '--cutover' => true,
    ])->assertSuccessful();

    expect(TicketLink::query()->where('connection_id', $binding->connection_id)->count())->toBe(1) // Done filtered
        ->and($binding->refresh()->sync_direction)->toBe(SyncDirection::Disabled);
});

test('the --queue flag dispatches a job per binding instead of importing inline', function (): void {
    Queue::fake();
    app(DriverRegistry::class)->register(new RecordingIssuesDriver(['1' => 'Open']));
    [$project, $type] = sync_fixture();
    $binding = sync_binding(SyncDirection::Inbound, $project, $type, ['Open' => 'open']);

    $this->artisan('sao:tracker:import', [
        'connection' => $binding->remoteConnection->name,
        '--queue' => true,
    ])->assertSuccessful();

    Queue::assertPushed(ImportTrackerHistoryJob::class, 1);
    expect(TicketLink::query()->count())->toBe(0);
});

test('an invalid scope fails the command', function (): void {
    app(DriverRegistry::class)->register(new RecordingIssuesDriver);
    [$project, $type] = sync_fixture();
    $binding = sync_binding(SyncDirection::Inbound, $project, $type);

    $this->artisan('sao:tracker:import', [
        'connection' => $binding->remoteConnection->name,
        '--scope' => 'nonsense',
    ])->assertFailed();
});
