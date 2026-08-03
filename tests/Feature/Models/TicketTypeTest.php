<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;

uses(RefreshDatabase::class);

test('a type owns a workflow scheme and can be flagged as a defect type', function (): void {
    $scheme = WorkflowScheme::factory()->create();

    $type = TicketType::factory()->create([
        'name' => 'Bug',
        'slug' => 'bug',
        'workflow_scheme_id' => $scheme->id,
        'is_defect' => true,
    ]);

    expect($type->scheme->id)->toBe($scheme->id);
    expect($type->is_defect)->toBeTrue();
});

test('a new type reports its defaults before being persisted', function (): void {
    $type = new TicketType;

    expect($type->colour)->toBe('gray');
    expect($type->is_defect)->toBeFalse();
});

test('the defect type is findable, which is how phase 2 will pick one', function (): void {
    TicketType::factory()->create(['slug' => 'task']);
    $bug = TicketType::factory()->defect()->create(['slug' => 'bug']);

    $found = TicketType::query()->where('is_defect', true)->get();

    expect($found)->toHaveCount(1);
    expect($found->first()?->id)->toBe($bug->id);
});

test('two types cannot share a slug', function (): void {
    TicketType::factory()->create(['slug' => 'bug']);

    expect(fn (): TicketType => TicketType::factory()->create(['slug' => 'bug']))
        ->toThrow(Modules\Core\Overrides\ContextualValidationException::class);
});

test('a project enables types through the pivot, with one marked default', function (): void {
    $project = Project::factory()->create();
    $bug = TicketType::factory()->create(['slug' => 'bug']);
    $task = TicketType::factory()->create(['slug' => 'task']);

    $project->ticketTypes()->attach($bug->id, ['is_default' => true]);
    $project->ticketTypes()->attach($task->id, ['is_default' => false]);

    expect($project->ticketTypes()->count())->toBe(2);
    expect($project->defaultTicketType()?->id)->toBe($bug->id);
});

test('the pivot may override the workflow scheme for one project', function (): void {
    $project = Project::factory()->create();
    $type_scheme = WorkflowScheme::factory()->create();
    $override = WorkflowScheme::factory()->create();
    $type = TicketType::factory()->create(['workflow_scheme_id' => $type_scheme->id]);

    $project->ticketTypes()->attach($type->id, [
        'is_default' => true,
        'workflow_scheme_id' => $override->id,
    ]);

    $pivot = $project->ticketTypes()->first()?->pivot;

    expect($pivot?->workflow_scheme_id)->toBe($override->id);
});

test('without an override the pivot leaves the scheme to the type', function (): void {
    $project = Project::factory()->create();
    $type = TicketType::factory()->create();

    $project->ticketTypes()->attach($type->id, ['is_default' => true]);

    expect($project->ticketTypes()->first()?->pivot->workflow_scheme_id)->toBeNull();
});
