<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Models\ChangeRef;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\ProjectBinding;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Tests\Support\Drivers\StubVcsDriver;

uses(RefreshDatabase::class);

test('the command scans a vcs binding and records change refs', function (): void {
    $project = Project::factory()->create();
    $ticket = Ticket::factory()->for($project)->create();

    app(DriverRegistry::class)->register(new StubVcsDriver([
        ['sha' => 'sha-1', 'message' => "Fixes {$ticket->key}"],
    ], 'v1.0.0', 'stub-vcs'));

    $connection = Connection::factory()->create([
        'name' => 'Acme VCS',
        'driver_key' => 'stub-vcs',
        'capabilities' => [Capability::Vcs, Capability::Releases],
        'base_url' => null,
        'credential' => ['token' => 'x'],
    ]);
    ProjectBinding::factory()->create([
        'project_id' => $project->getKey(),
        'connection_id' => $connection->getKey(),
        'capability' => Capability::Vcs,
        'remote_identifier' => 'acme/app',
    ]);

    $this->artisan('sao:vcs:scan', ['connection' => 'Acme VCS'])->assertSuccessful();

    expect(ChangeRef::query()->where('ticket_id', $ticket->id)->where('identifier', 'sha-1')->exists())->toBeTrue();
});

test('the command warns when no vcs binding matches', function (): void {
    $this->artisan('sao:vcs:scan')->assertSuccessful();
});
