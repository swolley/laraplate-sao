<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Exceptions\TransitionNotAllowedException;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;
use Modules\SAO\Models\WorkflowTransition;
use Modules\SAO\Services\WorkflowService;

uses(RefreshDatabase::class);

/**
 * A project with one type whose scheme is: (creation) -> Open -> Doing.
 * `blocked` exists but no transition reaches it, which is what makes it useful.
 *
 * @return array{project: Project, type: TicketType, open: TicketStatus, doing: TicketStatus, blocked: TicketStatus}
 */
function sao_workflow_fixture(): array
{
    $open = TicketStatus::factory()->category(StatusCategory::Open)->create(['name' => 'Open']);
    $doing = TicketStatus::factory()->category(StatusCategory::InProgress)->create(['name' => 'Doing']);
    $blocked = TicketStatus::factory()->category(StatusCategory::Open)->create(['name' => 'Blocked']);

    $scheme = WorkflowScheme::factory()->create(['name' => 'Simple']);
    WorkflowTransition::factory()->for($scheme, 'scheme')->create([
        'from_status_id' => null,
        'to_status_id' => $open->id,
        'label' => 'Open',
    ]);
    WorkflowTransition::factory()->for($scheme, 'scheme')->create([
        'from_status_id' => $open->id,
        'to_status_id' => $doing->id,
        'label' => 'Start work',
    ]);

    $type = TicketType::factory()->create(['workflow_scheme_id' => $scheme->id]);
    $project = Project::factory()->create();
    $project->ticketTypes()->attach($type->id, ['is_default' => true]);

    return compact('project', 'type', 'open', 'doing', 'blocked');
}

test('the scheme comes from the type when the project sets no override', function (): void {
    ['project' => $project, 'type' => $type] = sao_workflow_fixture();

    expect(app(WorkflowService::class)->schemeFor($project, $type)->id)
        ->toBe($type->workflow_scheme_id);
});

test('a project override wins over the type own scheme', function (): void {
    ['project' => $project, 'type' => $type] = sao_workflow_fixture();
    $override = WorkflowScheme::factory()->create(['name' => 'Override']);

    $project->ticketTypes()->updateExistingPivot($type->id, ['workflow_scheme_id' => $override->id]);

    expect(app(WorkflowService::class)->schemeFor($project->fresh(), $type)->id)->toBe($override->id);
});

test('the opening status comes from the creation transition', function (): void {
    ['project' => $project, 'type' => $type, 'open' => $open] = sao_workflow_fixture();

    expect(app(WorkflowService::class)->openingStatusFor($project, $type)->id)->toBe($open->id);
});

test('a scheme without a creation transition refuses to open a ticket', function (): void {
    $scheme = WorkflowScheme::factory()->create(['name' => 'Broken']);
    $type = TicketType::factory()->create(['workflow_scheme_id' => $scheme->id]);
    $project = Project::factory()->create();
    $project->ticketTypes()->attach($type->id, ['is_default' => true]);

    expect(fn (): TicketStatus => app(WorkflowService::class)->openingStatusFor($project, $type))
        ->toThrow(RuntimeException::class);
});

test('only the transitions declared by the scheme are offered', function (): void {
    ['project' => $project, 'type' => $type, 'open' => $open, 'doing' => $doing] = sao_workflow_fixture();

    $ticket = Ticket::factory()->forProject($project)->create([
        'ticket_type_id' => $type->id,
        'ticket_status_id' => $open->id,
    ]);

    $available = app(WorkflowService::class)->availableTransitions($ticket);

    expect($available)->toHaveCount(1);
    expect($available->first()?->to_status_id)->toBe($doing->id);
});

test('an undeclared transition is refused and leaves the ticket untouched', function (): void {
    ['project' => $project, 'type' => $type, 'open' => $open, 'blocked' => $blocked] = sao_workflow_fixture();

    $ticket = Ticket::factory()->forProject($project)->create([
        'ticket_type_id' => $type->id,
        'ticket_status_id' => $open->id,
    ]);

    $context = ChangeContext::forAutomation('test');

    expect(fn (): Ticket => app(WorkflowService::class)->transition($ticket, $blocked, $context))
        ->toThrow(TransitionNotAllowedException::class);

    expect($ticket->fresh()->ticket_status_id)->toBe($open->id);
});

test('a declared transition moves the ticket', function (): void {
    ['project' => $project, 'type' => $type, 'open' => $open, 'doing' => $doing] = sao_workflow_fixture();

    $ticket = Ticket::factory()->forProject($project)->create([
        'ticket_type_id' => $type->id,
        'ticket_status_id' => $open->id,
    ]);

    $moved = app(WorkflowService::class)->transition($ticket, $doing, ChangeContext::forAutomation('test'));

    expect($moved->ticket_status_id)->toBe($doing->id);
    expect($ticket->fresh()->ticket_status_id)->toBe($doing->id);
});

test('the override bypasses an undeclared transition', function (): void {
    ['project' => $project, 'type' => $type, 'open' => $open, 'blocked' => $blocked] = sao_workflow_fixture();

    $ticket = Ticket::factory()->forProject($project)->create([
        'ticket_type_id' => $type->id,
        'ticket_status_id' => $open->id,
    ]);

    $moved = app(WorkflowService::class)->transition(
        $ticket,
        $blocked,
        ChangeContext::forAutomation('test')->withOverride(),
    );

    expect($moved->ticket_status_id)->toBe($blocked->id);
});

test('a context built for a user carries that user as the actor', function (): void {
    $user = User::factory()->create();

    $context = ChangeContext::forUser($user);

    expect($context->userId())->toBe($user->id);
    expect($context->sourceKey())->toBeNull();
    expect($context->hasOverride())->toBeFalse();
});

test('a context built for automation names its source and has no user', function (): void {
    $context = ChangeContext::forAutomation('ingest');

    expect($context->sourceKey())->toBe('ingest');
    expect($context->userId())->toBeNull();
});
