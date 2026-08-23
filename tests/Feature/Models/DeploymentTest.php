<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\DeploymentStatus;
use Modules\SAO\Models\Deployment;
use Modules\SAO\Models\Environment;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Release;

uses(RefreshDatabase::class);

test('a deployment belongs to its project, environment and release', function (): void {
    $project = Project::factory()->create();
    $environment = Environment::factory()->for($project)->create(['name' => 'production']);
    $release = Release::factory()->for($project)->create(['version' => '1.0.0']);

    $deployment = Deployment::factory()->for($project)->succeeded()->create([
        'environment_id' => $environment->id,
        'release_id' => $release->id,
        'version' => '1.0.0',
    ]);

    expect($deployment->project->is($project))->toBeTrue()
        ->and($deployment->environment->is($environment))->toBeTrue()
        ->and($deployment->release->is($release))->toBeTrue()
        ->and($deployment->status)->toBe(DeploymentStatus::Succeeded)
        ->and($deployment->finished_at)->not->toBeNull();
});

test('deployment status knows terminal and successful outcomes', function (): void {
    expect(DeploymentStatus::Started->isTerminal())->toBeFalse()
        ->and(DeploymentStatus::Started->isSuccessful())->toBeFalse()
        ->and(DeploymentStatus::Succeeded->isTerminal())->toBeTrue()
        ->and(DeploymentStatus::Succeeded->isSuccessful())->toBeTrue()
        ->and(DeploymentStatus::RolledBack->isTerminal())->toBeTrue()
        ->and(DeploymentStatus::RolledBack->isSuccessful())->toBeFalse()
        ->and(DeploymentStatus::Failed->isTerminal())->toBeTrue()
        ->and(DeploymentStatus::Superseded->isTerminal())->toBeTrue();
});
