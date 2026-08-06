<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Enums\TicketPriority;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;
use Modules\SAO\Models\WorkflowTransition;
use Modules\SAO\Services\TicketCreationService;

uses(RefreshDatabase::class);

/**
 * @return array{project: Project, type: TicketType, open: TicketStatus}
 */
function sao_creation_fixture(): array
{
    $open = TicketStatus::factory()->category(StatusCategory::Open)->create(['name' => 'Open']);

    $scheme = WorkflowScheme::factory()->create(['name' => 'Simple']);
    WorkflowTransition::factory()->for($scheme, 'scheme')->create([
        'from_status_id' => null,
        'to_status_id' => $open->id,
        'label' => 'Open',
    ]);

    $type = TicketType::factory()->create(['workflow_scheme_id' => $scheme->id]);
    $project = Project::factory()->create(['key_prefix' => 'SAO']);
    $project->ticketTypes()->attach($type->id, ['is_default' => true]);

    return compact('project', 'type', 'open');
}

test('opening a ticket allocates its key and asks the scheme for the status', function (): void {
    ['project' => $project, 'type' => $type, 'open' => $open] = sao_creation_fixture();
    $user = User::factory()->create();

    $ticket = app(TicketCreationService::class)->open(
        $project,
        $type,
        ['title' => 'Something broke'],
        ChangeContext::forUser($user),
    );

    expect($ticket->key)->toBe('SAO-1');
    expect($ticket->number)->toBe(1);
    expect($ticket->ticket_status_id)->toBe($open->id);
    expect($ticket->reporter_id)->toBe($user->id);
    expect($ticket->priority)->toBe(TicketPriority::Normal);
});

test('consecutive tickets in a project get consecutive keys', function (): void {
    ['project' => $project, 'type' => $type] = sao_creation_fixture();
    $context = ChangeContext::forAutomation('test');
    $service = app(TicketCreationService::class);

    $first = $service->open($project, $type, ['title' => 'One'], $context);
    $second = $service->open($project, $type, ['title' => 'Two'], $context);

    expect([$first->key, $second->key])->toBe(['SAO-1', 'SAO-2']);
});

test('a ticket opened by automation has no reporter', function (): void {
    ['project' => $project, 'type' => $type] = sao_creation_fixture();

    $ticket = app(TicketCreationService::class)->open(
        $project,
        $type,
        ['title' => 'From an error'],
        ChangeContext::forAutomation('ingest'),
    );

    expect($ticket->reporter_id)->toBeNull();
});

/**
 * The opening status is asked of the scheme, never defaulted. A scheme that
 * declares no creation transition must fail here rather than produce a ticket in
 * a status its own workflow does not know.
 */
test('a scheme without a creation transition refuses to open a ticket', function (): void {
    $scheme = WorkflowScheme::factory()->create(['name' => 'Broken']);
    $type = TicketType::factory()->create(['workflow_scheme_id' => $scheme->id]);
    $project = Project::factory()->create(['key_prefix' => 'BRK']);
    $project->ticketTypes()->attach($type->id, ['is_default' => true]);

    expect(fn (): Ticket => app(TicketCreationService::class)->open(
        $project,
        $type,
        ['title' => 'Nowhere to start'],
        ChangeContext::forAutomation('test'),
    ))->toThrow(RuntimeException::class);

    expect(Ticket::query()->count())->toBe(0);
});

test('the caller may set priority and description', function (): void {
    ['project' => $project, 'type' => $type] = sao_creation_fixture();

    $ticket = app(TicketCreationService::class)->open(
        $project,
        $type,
        ['title' => 'Urgent', 'description' => 'Details', 'priority' => TicketPriority::Urgent],
        ChangeContext::forAutomation('test'),
    );

    expect($ticket->priority)->toBe(TicketPriority::Urgent);
    expect($ticket->description)->toBe('Details');
});
