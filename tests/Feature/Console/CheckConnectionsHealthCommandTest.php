<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\ConnectionHealth;
use Modules\SAO\Models\Connection;

uses(RefreshDatabase::class);

function githubConnectionNamed(string $name): Connection
{
    return Connection::factory()->create([
        'name' => $name,
        'driver_key' => 'github',
        'base_url' => 'https://api.github.com',
        'credential' => ['token' => 'ghp_secret'],
        'capabilities' => [Capability::Issues],
    ]);
}

test('it probes every connection and records health, succeeding when all are healthy', function (): void {
    Http::fake(['*/rate_limit' => Http::response(['resources' => []], 200)]);
    $connection = githubConnectionNamed('Acme GitHub');

    $this->artisan('sao:connection:health')->assertSuccessful();

    expect($connection->fresh()->health_state)->toBe(ConnectionHealth::Healthy);
});

test('it fails when a connection is unhealthy', function (): void {
    Http::fake(['*/rate_limit' => Http::response([], 500)]);
    githubConnectionNamed('Acme GitHub');

    $this->artisan('sao:connection:health')->assertFailed();
});

test('it can target a single connection by name', function (): void {
    Http::fake(['*/rate_limit' => Http::response(['resources' => []], 200)]);
    $target = githubConnectionNamed('Target');
    $other = githubConnectionNamed('Other');

    $this->artisan('sao:connection:health', ['name' => 'Target'])->assertSuccessful();

    expect($target->fresh()->health_state)->toBe(ConnectionHealth::Healthy)
        ->and($other->fresh()->health_state)->toBe(ConnectionHealth::Unknown);
});

test('it fails when the named connection does not exist', function (): void {
    $this->artisan('sao:connection:health', ['name' => 'Nope'])->assertFailed();
});
