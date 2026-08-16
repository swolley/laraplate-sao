<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Data\EnvironmentCensus;
use Modules\SAO\Models\Environment;
use Modules\SAO\Models\Project;
use Modules\SAO\Services\DeployCensusService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new DeployCensusService();
});

test('observing a version records it with a fresh timestamp', function (): void {
    $environment = Environment::factory()->create(['current_version' => null, 'last_seen_at' => null]);

    $this->service->observe($environment, '1.4.0');

    expect($environment->refresh()->current_version)->toBe('1.4.0')
        ->and($environment->last_seen_at)->not->toBeNull()
        ->and($this->service->isStale($environment, 60))->toBeFalse();
});

test('probing records the checked version like an observation', function (): void {
    $environment = Environment::factory()->create();

    $this->service->recordProbe($environment, '2.0.0');

    expect($environment->refresh()->current_version)->toBe('2.0.0')
        ->and($environment->last_seen_at)->not->toBeNull();
});

test('an environment never seen is stale', function (): void {
    $environment = Environment::factory()->create(['last_seen_at' => null]);

    expect($this->service->isStale($environment, 60))->toBeTrue();
});

test('staleness flips once the last sighting is older than the ttl', function (): void {
    $environment = Environment::factory()->create();
    $this->service->observe($environment, '1.0.0');
    $environment->refresh();

    expect($this->service->isStale($environment, 60))->toBeFalse();

    $environment->forceFill(['last_seen_at' => now()->subMinutes(90)])->save();

    expect($this->service->isStale($environment->refresh(), 60))->toBeTrue();
});

test('the census reports each environment version and freshness', function (): void {
    $project = Project::factory()->create();
    $production = Environment::factory()->for($project)->create(['name' => 'production']);
    $staging = Environment::factory()->for($project)->create(['name' => 'staging']);

    $this->service->observe($production, '1.4.0');
    $staging->forceFill(['current_version' => '1.5.0-rc.1', 'last_seen_at' => now()->subMinutes(120)])->save();

    $census = $this->service->census($project->refresh(), 60);

    expect($census)->toHaveCount(2)
        ->and($census->every(fn (EnvironmentCensus $row): bool => $row instanceof EnvironmentCensus))->toBeTrue();

    $rows = $census->keyBy(fn (EnvironmentCensus $row): string => $row->environment);

    expect($rows['production']->version)->toBe('1.4.0')
        ->and($rows['production']->is_stale)->toBeFalse()
        ->and($rows['staging']->version)->toBe('1.5.0-rc.1')
        ->and($rows['staging']->is_stale)->toBeTrue();
});
