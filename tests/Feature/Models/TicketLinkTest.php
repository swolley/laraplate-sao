<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketLink;
use Modules\SAO\Tests\Support\Drivers\FakeIssuesDriver;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(DriverRegistry::class)->register(new FakeIssuesDriver);
});

test('a ticket link persists and enforces connection+remote uniqueness', function (): void {
    $ticket = Ticket::factory()->create();
    $connection = Connection::factory()->create(['driver_key' => 'fake', 'capabilities' => [Capability::Issues]]);

    $attributes = [
        'ticket_id' => $ticket->id,
        'connection_id' => $connection->id,
        'remote_id' => 'REDMINE-42',
        'url' => 'https://tracker.test/issues/42',
    ];

    TicketLink::factory()->create($attributes);

    expect(fn (): TicketLink => TicketLink::factory()->create($attributes))
        ->toThrow(QueryException::class);
});

test('a ticket with no link is internal', function (): void {
    $ticket = Ticket::factory()->create();
    $connection = Connection::factory()->create(['driver_key' => 'fake', 'capabilities' => [Capability::Issues]]);

    expect($ticket->isInternal())->toBeTrue();

    TicketLink::factory()->create([
        'ticket_id' => $ticket->id,
        'connection_id' => $connection->id,
        'remote_id' => 'REDMINE-7',
    ]);

    expect($ticket->fresh()->isInternal())->toBeFalse();
});
