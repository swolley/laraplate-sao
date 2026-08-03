<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Exceptions\DuplicateCreationTransitionException;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\WorkflowScheme;
use Modules\SAO\Models\WorkflowTransition;

uses(RefreshDatabase::class);

test('a scheme owns its transitions', function (): void {
    $scheme = WorkflowScheme::factory()->create();
    $open = TicketStatus::factory()->category(StatusCategory::Open)->create();
    $doing = TicketStatus::factory()->category(StatusCategory::InProgress)->create();

    WorkflowTransition::factory()->for($scheme, 'scheme')->create([
        'from_status_id' => $open->id,
        'to_status_id' => $doing->id,
        'label' => 'Start work',
    ]);

    expect($scheme->transitions()->count())->toBe(1);
});

test('the transition with no source status is the creation transition', function (): void {
    $scheme = WorkflowScheme::factory()->create();
    $open = TicketStatus::factory()->category(StatusCategory::Open)->create();

    $initial = WorkflowTransition::factory()->for($scheme, 'scheme')->create([
        'from_status_id' => null,
        'to_status_id' => $open->id,
        'label' => 'Open',
    ]);

    expect($scheme->initialTransition()?->id)->toBe($initial->id);
    expect($scheme->initialTransition()?->to_status_id)->toBe($open->id);
    expect($initial->isInitial())->toBeTrue();
});

/**
 * The composite unique index cannot express this: SQL treats rows with a null
 * from_status_id as distinct, so the guard lives in the model.
 */
test('a scheme may declare only one creation transition', function (): void {
    $scheme = WorkflowScheme::factory()->create();
    $open = TicketStatus::factory()->create();
    $other = TicketStatus::factory()->create();

    WorkflowTransition::factory()->for($scheme, 'scheme')->create([
        'from_status_id' => null,
        'to_status_id' => $open->id,
    ]);

    expect(fn (): WorkflowTransition => WorkflowTransition::factory()->for($scheme, 'scheme')->create([
        'from_status_id' => null,
        'to_status_id' => $other->id,
    ]))->toThrow(DuplicateCreationTransitionException::class);
});

test('two schemes may each declare their own creation transition', function (): void {
    $first = WorkflowScheme::factory()->create();
    $second = WorkflowScheme::factory()->create();
    $open = TicketStatus::factory()->create();

    WorkflowTransition::factory()->for($first, 'scheme')->create([
        'from_status_id' => null,
        'to_status_id' => $open->id,
    ]);

    $other = WorkflowTransition::factory()->for($second, 'scheme')->create([
        'from_status_id' => null,
        'to_status_id' => $open->id,
    ]);

    expect($other->exists)->toBeTrue();
});

test('exactly one scheme is the default', function (): void {
    $first = WorkflowScheme::factory()->create(['is_default' => true]);
    $second = WorkflowScheme::factory()->create(['is_default' => true]);

    expect(WorkflowScheme::query()->where('is_default', true)->count())->toBe(1);
    expect(WorkflowScheme::default()?->id)->toBe($second->id);
    expect($first->fresh()->is_default)->toBeFalse();
});

test('a new scheme is not the default until it says so', function (): void {
    expect((new WorkflowScheme)->is_default)->toBeFalse();
});
