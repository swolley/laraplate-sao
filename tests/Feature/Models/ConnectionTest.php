<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\ConnectionHealth;
use Modules\SAO\Exceptions\UnsupportedCapabilityException;
use Modules\SAO\Models\Connection;
use Modules\SAO\Tests\Support\Drivers\FakeIssuesDriver;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(DriverRegistry::class)->register(new FakeIssuesDriver);
});

test('a connection persists with its capabilities as enum instances', function (): void {
    $fresh = Connection::factory()->create([
        'driver_key' => 'fake',
        'capabilities' => [Capability::Issues],
    ])->fresh();

    expect($fresh->capabilities->all())->toContain(Capability::Issues)
        ->and($fresh->health_state)->toBe(ConnectionHealth::Unknown);
});

test('the credential column is encrypted at rest', function (): void {
    $fresh = Connection::factory()->create([
        'driver_key' => 'fake',
        'capabilities' => [Capability::Issues],
        'credential' => ['token' => 'supersecret'],
    ])->fresh();

    expect($fresh->credential)->toBe(['token' => 'supersecret'])
        ->and($fresh->getRawOriginal('credential'))->not->toContain('supersecret');
});

test('declaring a capability the driver does not expose is rejected', function (): void {
    expect(fn (): Connection => Connection::factory()->create([
        'driver_key' => 'fake',
        'capabilities' => [Capability::Issues, Capability::Vcs],
    ]))->toThrow(UnsupportedCapabilityException::class);
});

test('a connection resolves its driver from the registry', function (): void {
    $connection = Connection::factory()->create([
        'driver_key' => 'fake',
        'capabilities' => [Capability::Issues],
    ]);

    expect($connection->driver(app(DriverRegistry::class))->key())->toBe('fake');
});
