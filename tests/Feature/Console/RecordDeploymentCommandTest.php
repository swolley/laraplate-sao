<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\DeploymentStatus;
use Modules\SAO\Models\Deployment;
use Modules\SAO\Models\Environment;
use Modules\SAO\Models\Project;

uses(RefreshDatabase::class);

test('the command records a deployment and advances the census', function (): void {
    $project = Project::factory()->create(['name' => 'Acme']);

    $this->artisan('sao:deploy:record', [
        'project' => 'Acme',
        'version' => '1.2.3',
        '--env' => 'production',
        '--status' => 'succeeded',
    ])->assertSuccessful();

    $deployment = Deployment::query()->where('project_id', $project->id)->firstOrFail();
    $environment = Environment::query()->where('project_id', $project->id)->where('name', 'production')->first();

    expect($deployment->version)->toBe('1.2.3')
        ->and($deployment->status)->toBe(DeploymentStatus::Succeeded)
        ->and($environment->current_version)->toBe('1.2.3');
});

test('the command is idempotent for a repeated external id', function (): void {
    $project = Project::factory()->create(['name' => 'Acme']);

    foreach (['started', 'succeeded'] as $status) {
        $this->artisan('sao:deploy:record', [
            'project' => 'Acme',
            'version' => '2.0.0',
            '--env' => 'production',
            '--status' => $status,
            '--external-id' => 'ci-run-42',
        ])->assertSuccessful();
    }

    expect(Deployment::query()->where('project_id', $project->id)->count())->toBe(1)
        ->and(Deployment::query()->where('project_id', $project->id)->firstOrFail()->status)
        ->toBe(DeploymentStatus::Succeeded);
});

test('an unknown project fails the command', function (): void {
    $this->artisan('sao:deploy:record', [
        'project' => 'Nope',
        'version' => '1.0.0',
    ])->assertFailed();
});

test('an invalid status fails the command', function (): void {
    Project::factory()->create(['name' => 'Acme']);

    $this->artisan('sao:deploy:record', [
        'project' => 'Acme',
        'version' => '1.0.0',
        '--status' => 'exploded',
    ])->assertFailed();
});
