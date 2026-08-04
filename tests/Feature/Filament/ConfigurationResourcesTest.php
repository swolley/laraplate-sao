<?php

declare(strict_types=1);

use Modules\SAO\Filament\Resources\Projects\ProjectResource;
use Modules\SAO\Filament\Resources\TicketStatuses\TicketStatusResource;
use Modules\SAO\Filament\Resources\TicketTypes\TicketTypeResource;
use Modules\SAO\Filament\Resources\WorkflowSchemes\WorkflowSchemeResource;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;

test('each configuration resource is bound to its model', function (string $resource, string $model): void {
    expect($resource::getModel())->toBe($model);
})->with([
    [ProjectResource::class, Project::class],
    [TicketStatusResource::class, TicketStatus::class],
    [TicketTypeResource::class, TicketType::class],
    [WorkflowSchemeResource::class, WorkflowScheme::class],
]);

test('every SAO resource is grouped under SAO in the navigation', function (string $resource): void {
    expect($resource::getNavigationGroup())->toBe('SAO');
})->with([
    ProjectResource::class,
    TicketStatusResource::class,
    TicketTypeResource::class,
    WorkflowSchemeResource::class,
]);

test('every SAO resource has a stable slug under the sao prefix', function (string $resource): void {
    expect($resource::getSlug())->toStartWith('sao/');
})->with([
    ProjectResource::class,
    TicketStatusResource::class,
    TicketTypeResource::class,
    WorkflowSchemeResource::class,
]);

/**
 * Platform bookkeeping must not reach the form. is_deleted is added by
 * MigrateUtils alongside deleted_at and the lock version by optimistic locking;
 * HasForm injects the latter as a hidden component, so a generated field would
 * duplicate it.
 */
test('no resource form exposes a platform bookkeeping column', function (string $path): void {
    $contents = (string) file_get_contents($path);

    expect($contents)->not->toContain("make('is_deleted')");
    expect($contents)->not->toContain("make('lock_version')");
})->with(fn (): array => glob(dirname(__DIR__, 3) . '/app/Filament/Resources/*/Schemas/*.php') ?: []);

test('the workflow scheme resource exposes its transitions as a relation', function (): void {
    expect(WorkflowSchemeResource::getRelations())->not->toBeEmpty();
});
