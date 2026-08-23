<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\ChangeRefType;
use Modules\SAO\Enums\ClosureAction;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Enums\TicketReleaseState;
use Modules\SAO\Models\ChangeRef;
use Modules\SAO\Models\ClosurePolicy;
use Modules\SAO\Models\Environment;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketRelease;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;
use Modules\SAO\Models\WorkflowTransition;
use Modules\SAO\Services\ClosureCoordinator;

uses(RefreshDatabase::class);

/**
 * A ticket ready to close: Open → Done(closed) scheme, a merged fixing PR, a
 * shipped release deployed on production, and an active close policy whose
 * deploy-based conditions hold.
 *
 * @return array{ticket: Ticket, done: TicketStatus}
 */
function coord_closure_fixture(): array
{
    $open = TicketStatus::factory()->category(StatusCategory::Open)->create(['name' => 'Open']);
    $done = TicketStatus::factory()->category(StatusCategory::Closed)->create(['name' => 'Done']);

    $scheme = WorkflowScheme::factory()->create(['name' => 'Closable']);
    WorkflowTransition::factory()->for($scheme, 'scheme')->create(['from_status_id' => null, 'to_status_id' => $open->id, 'label' => 'Open']);
    WorkflowTransition::factory()->for($scheme, 'scheme')->create(['from_status_id' => $open->id, 'to_status_id' => $done->id, 'label' => 'Close']);

    $type = TicketType::factory()->create(['workflow_scheme_id' => $scheme->id]);
    $project = Project::factory()->create();
    $project->ticketTypes()->attach($type->id, ['is_default' => true]);

    $ticket = Ticket::factory()->forProject($project)->create(['ticket_type_id' => $type->id, 'ticket_status_id' => $open->id]);

    ChangeRef::factory()->create(['ticket_id' => $ticket->id, 'type' => ChangeRefType::PullRequest, 'identifier' => '10', 'merged_at' => now()]);
    $release = Release::factory()->for($project)->shipped()->create(['version' => '1.0.0']);
    TicketRelease::factory()->create(['ticket_id' => $ticket->id, 'release_id' => $release->id, 'state' => TicketReleaseState::Shipped]);
    Environment::factory()->for($project)->create(['name' => 'production', 'current_version' => '1.0.0']);

    ClosurePolicy::factory()->for($project)->closes()->create([
        'conditions' => [['key' => 'pull_request_merged'], ['key' => 'fix_deployed_there']],
    ]);

    return ['ticket' => $ticket, 'done' => $done];
}

test('with auto-close off a satisfied close policy only proposes', function (): void {
    config(['sao.closure.auto_close.enabled' => false]);
    ['ticket' => $ticket, 'done' => $done] = coord_closure_fixture();

    $audits = app(ClosureCoordinator::class)->forTicket($ticket->refresh(), 'production');

    expect($audits)->toHaveCount(1)
        ->and($audits[0]->action)->toBe(ClosureAction::Propose)
        ->and($ticket->refresh()->ticket_status_id)->not->toBe($done->id);
});

test('with auto-close on a satisfied close policy closes the ticket', function (): void {
    config(['sao.closure.auto_close.enabled' => true]);
    ['ticket' => $ticket, 'done' => $done] = coord_closure_fixture();

    $audits = app(ClosureCoordinator::class)->forTicket($ticket->refresh(), 'production');

    expect($audits)->toHaveCount(1)
        ->and($audits[0]->action)->toBe(ClosureAction::Close)
        ->and($ticket->refresh()->ticket_status_id)->toBe($done->id);
});

test('the downgrade does not persist the policy action', function (): void {
    config(['sao.closure.auto_close.enabled' => false]);
    ['ticket' => $ticket] = coord_closure_fixture();

    app(ClosureCoordinator::class)->forTicket($ticket->refresh(), 'production');

    expect(ClosurePolicy::query()->where('project_id', $ticket->project_id)->sole()->action)
        ->toBe(ClosureAction::Close);
});
