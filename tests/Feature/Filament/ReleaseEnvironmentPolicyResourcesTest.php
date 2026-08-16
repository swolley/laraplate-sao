<?php

declare(strict_types=1);

use Modules\SAO\Filament\Resources\ClosurePolicies\ClosurePolicyResource;
use Modules\SAO\Filament\Resources\Environments\EnvironmentResource;
use Modules\SAO\Filament\Resources\Releases\ReleaseResource;
use Modules\SAO\Models\ClosurePolicy;
use Modules\SAO\Models\Environment;
use Modules\SAO\Models\Release;

test('each phase 5b/6 resource is bound to its model', function (string $resource, string $model): void {
    expect($resource::getModel())->toBe($model);
})->with([
    [ReleaseResource::class, Release::class],
    [EnvironmentResource::class, Environment::class],
    [ClosurePolicyResource::class, ClosurePolicy::class],
]);

test('each phase 5b/6 resource is grouped under SAO with a sao-prefixed slug', function (string $resource): void {
    expect($resource::getNavigationGroup())->toBe('SAO')
        ->and($resource::getSlug())->toStartWith('sao/');
})->with([
    ReleaseResource::class,
    EnvironmentResource::class,
    ClosurePolicyResource::class,
]);

test('each phase 5b/6 resource exposes the full CRUD page set', function (string $resource): void {
    expect(array_keys($resource::getPages()))->toBe(['index', 'create', 'edit']);
})->with([
    ReleaseResource::class,
    EnvironmentResource::class,
    ClosurePolicyResource::class,
]);

test('the release resource exposes its tags as a relation', function (): void {
    expect(ReleaseResource::getRelations())->not->toBeEmpty();
});

test('the closure policy form lists every registered condition key', function (): void {
    $path = dirname(__DIR__, 3) . '/app/Filament/Resources/ClosurePolicies/Schemas/ClosurePolicyForm.php';
    $contents = (string) file_get_contents($path);

    foreach (['pull_request_merged', 'no_recurrence_for', 'fix_released', 'fix_deployed_there', 'resolved_for', 'internal_tickets_only'] as $key) {
        expect($contents)->toContain("'{$key}'");
    }
});
