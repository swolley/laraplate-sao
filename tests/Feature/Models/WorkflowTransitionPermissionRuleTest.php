<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Permission;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\WorkflowScheme;
use Modules\SAO\Models\WorkflowTransition;

uses(RefreshDatabase::class);

/**
 * `required_permission` is handed straight to `Gate::allows()` by WorkflowService,
 * which fails closed on a name that does not exist. A typo used to deny the transition
 * for everybody, permanently, and nothing anywhere said so. The name is checked on the
 * way in now, so the mistake surfaces where it is made.
 */
function transitionForPermissionRule(): WorkflowTransition
{
    $open = TicketStatus::factory()->category(StatusCategory::Open)->create(['name' => 'Open']);
    $doing = TicketStatus::factory()->category(StatusCategory::InProgress)->create(['name' => 'Doing']);
    $scheme = WorkflowScheme::factory()->create(['name' => 'Rule check']);

    return WorkflowTransition::factory()->for($scheme, 'scheme')->create([
        'from_status_id' => $open->id,
        'to_status_id' => $doing->id,
        'label' => 'Start work',
    ]);
}

it('refuses a permission name nothing answers to', function (): void {
    $transition = transitionForPermissionRule();

    expect(fn (): bool => $transition->update(['required_permission' => 'default.sao_tickets.assgin']))
        ->toThrow(ValidationException::class);
});

it('accepts a permission that exists', function (): void {
    $name = 'default.sao_tickets.transition_override';
    Permission::findOrCreate($name, 'web');

    $transition = transitionForPermissionRule();
    $transition->update(['required_permission' => $name]);

    expect($transition->fresh()?->required_permission)->toBe($name);
});

it('accepts no permission at all', function (): void {
    $transition = transitionForPermissionRule();
    $transition->update(['required_permission' => null]);

    expect($transition->fresh()?->required_permission)->toBeNull();
});
