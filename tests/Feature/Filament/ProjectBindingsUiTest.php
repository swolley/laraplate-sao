<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Filament\Resources\Projects\ProjectResource;
use Modules\SAO\Filament\Resources\Projects\RelationManagers\BindingsRelationManager;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\ProjectBinding;
use Modules\SAO\Tests\Support\Drivers\FakeIssuesDriver;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(DriverRegistry::class)->register(new FakeIssuesDriver);
});

test('a project lists its bindings', function (): void {
    $project = Project::factory()->create();
    $connection = Connection::factory()->create([
        'driver_key' => 'fake',
        'capabilities' => [Capability::Issues],
    ]);

    $binding = ProjectBinding::factory()->create([
        'project_id' => $project->id,
        'connection_id' => $connection->id,
        'capability' => Capability::Issues,
    ]);

    expect($project->bindings()->pluck('id')->all())->toBe([$binding->id]);
});

test('the project resource registers the bindings relation manager', function (): void {
    expect(ProjectResource::getRelations())->toContain(BindingsRelationManager::class)
        ->and(class_exists(BindingsRelationManager::class))->toBeTrue();
});

test('the bindings relation manager edits the bindings relation', function (): void {
    $property = new ReflectionProperty(BindingsRelationManager::class, 'relationship');

    expect($property->getValue())->toBe('bindings');
});

test('the bindings form exposes connection, capability, direction and maps', function (): void {
    $source = (string) file_get_contents(
        dirname(__DIR__, 3) . '/app/Filament/Resources/Projects/RelationManagers/BindingsRelationManager.php',
    );

    expect($source)->toContain("Select::make('connection_id')")
        ->and($source)->toContain("Select::make('capability')")
        ->and($source)->toContain("Select::make('sync_direction')")
        ->and($source)->toContain("make('remote_identifier')")
        ->and($source)->toContain("KeyValue::make('status_map')")
        ->and($source)->toContain("KeyValue::make('priority_map')");
});
