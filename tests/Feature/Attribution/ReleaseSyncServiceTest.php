<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Attribution\ReleaseRegistrar;
use Modules\SAO\Attribution\ReleaseSyncService;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\ReleaseStatus;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\ProjectBinding;
use Modules\SAO\Models\Release;
use Modules\SAO\Tests\Support\Drivers\StubVcsDriver;

uses(RefreshDatabase::class);

/**
 * @param  list<string>  $tags
 */
function bindReleasesSync(array $tags, Project $project): ProjectBinding
{
    app(\Modules\SAO\Drivers\DriverRegistry::class)->register(new StubVcsDriver([], null, 'stub-vcs', $tags));

    $connection = Connection::factory()->create([
        'driver_key' => 'stub-vcs',
        'capabilities' => [Capability::Vcs, Capability::Releases],
        'base_url' => null,
        'credential' => ['token' => 'x'],
    ]);

    return ProjectBinding::factory()->create([
        'project_id' => $project->getKey(),
        'connection_id' => $connection->getKey(),
        'capability' => Capability::Releases,
        'remote_identifier' => 'acme/app',
    ]);
}

test('a newly-cut stable tag promotes the announced release known only from an RC', function (): void {
    $project = Project::factory()->create();

    // A release so far known only from a candidate: announced, not shipped.
    app(ReleaseRegistrar::class)->register($project->id, 'v1.4.0-rc.1', createIfMissing: true);
    expect(Release::query()->withoutGlobalScopes()->where('project_id', $project->id)->sole()->status)
        ->toBe(ReleaseStatus::Announced);

    $binding = bindReleasesSync(['v1.4.0-rc.1', 'v1.4.0', 'v9.9.9'], $project);

    $report = app(ReleaseSyncService::class)->sync($binding);

    expect($report->processed)->toBeTrue()
        ->and($report->tagsScanned)->toBe(3)
        ->and($report->tagsRegistered)->toBe(2)  // rc.1 + stable both map to release 1.4.0
        ->and($report->releasesPromoted)->toBe(1);

    $release = Release::query()->withoutGlobalScopes()->where('project_id', $project->id)->sole();

    expect($release->version)->toBe('1.4.0')
        ->and($release->status)->toBe(ReleaseStatus::Shipped)
        ->and($release->released_at)->not->toBeNull();

    // A tag with no attribution creates no release.
    expect(Release::query()->withoutGlobalScopes()->where('version', '9.9.9')->exists())->toBeFalse();
});

test('re-syncing does not re-promote an already-shipped release', function (): void {
    $project = Project::factory()->create();
    app(ReleaseRegistrar::class)->register($project->id, 'v1.4.0-rc.1', createIfMissing: true);
    $binding = bindReleasesSync(['v1.4.0'], $project);

    app(ReleaseSyncService::class)->sync($binding);
    $second = app(ReleaseSyncService::class)->sync($binding);

    expect($second->releasesPromoted)->toBe(0);
});

test('a non-releases binding is skipped', function (): void {
    $project = Project::factory()->create();
    $binding = bindReleasesSync(['v1.0.0'], $project);
    $binding->update(['capability' => Capability::Vcs]);

    expect(app(ReleaseSyncService::class)->sync($binding->refresh())->processed)->toBeFalse();
});
