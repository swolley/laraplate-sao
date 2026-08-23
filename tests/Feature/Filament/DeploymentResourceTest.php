<?php

declare(strict_types=1);

use Modules\SAO\Filament\Resources\Deployments\DeploymentResource;
use Modules\SAO\Models\Deployment;

function sao_deployment_source(string $relativePath): string
{
    return (string) file_get_contents(dirname(__DIR__, 3) . '/app/Filament/Resources/Deployments/' . $relativePath);
}

test('the deployment resource is bound to its model under the SAO group', function (): void {
    expect(DeploymentResource::getModel())->toBe(Deployment::class)
        ->and(DeploymentResource::getNavigationGroup())->toBe('SAO')
        ->and(DeploymentResource::getSlug())->toStartWith('sao/');
});

test('the deployment resource is read-only: list and view, no create or edit', function (): void {
    expect(array_keys(DeploymentResource::getPages()))->toBe(['index', 'view'])
        ->and(DeploymentResource::canCreate())->toBeFalse();
});

test('the deployment list offers no create action', function (): void {
    $contents = sao_deployment_source('Pages/ListDeployments.php');

    expect($contents)->toContain('getHeaderActions')
        ->and($contents)->toContain('return [];');
});

test('the table surfaces version, status, environment and timing columns', function (): void {
    $table = sao_deployment_source('Tables/DeploymentsTable.php');

    expect($table)->toContain("TextColumn::make('version')")
        ->and($table)->toContain("TextColumn::make('status')")
        ->and($table)->toContain("TextColumn::make('environment.name')")
        ->and($table)->toContain("TextColumn::make('finished_at')");
});

test('the infolist renders the meta blob alongside the deploy detail', function (): void {
    $infolist = sao_deployment_source('Schemas/DeploymentInfolist.php');

    expect($infolist)->toContain("TextEntry::make('meta')")
        ->and($infolist)->toContain('JSON_PRETTY_PRINT')
        ->and($infolist)->toContain("TextEntry::make('external_id')");
});
