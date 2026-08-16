<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\ChangeRefType;
use Modules\SAO\Enums\ClosureAction;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Enums\TicketReleaseState;
use Modules\SAO\Exceptions\ClosureTransitionUnavailableException;
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
use Modules\SAO\Services\ClosureApplicationService;

uses(RefreshDatabase::class);

/**
 * A project whose scheme is (creation) -> Open -> Done(closed). The ticket has
 * a merged PR and a shipped release deployed on production, so the deploy-based
 * conditions hold.
 *
 * @return array{ticket: Ticket, open: TicketStatus, done: TicketStatus}
 */
function closure_workflow_fixture(bool $withClosedTransition = true): array
{
    $open = TicketStatus::factory()->category(StatusCategory::Open)->create(['name' => 'Open']);
    $done = TicketStatus::factory()->category(StatusCategory::Closed)->create(['name' => 'Done']);

    $scheme = WorkflowScheme::factory()->create(['name' => 'Closable']);
    WorkflowTransition::factory()->for($scheme, 'scheme')->create([
        'from_status_id' => null,
        'to_status_id' => $open->id,
        'label' => 'Open',
    ]);

    if ($withClosedTransition) {
        WorkflowTransition::factory()->for($scheme, 'scheme')->create([
            'from_status_id' => $open->id,
            'to_status_id' => $done->id,
            'label' => 'Close',
        ]);
    }

    $type = TicketType::factory()->create(['workflow_scheme_id' => $scheme->id]);
    $project = Project::factory()->create();
    $project->ticketTypes()->attach($type->id, ['is_default' => true]);

    $ticket = Ticket::factory()->forProject($project)->create([
        'ticket_type_id' => $type->id,
        'ticket_status_id' => $open->id,
    ]);

    ChangeRef::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ChangeRefType::PullRequest,
        'identifier' => '10',
        'merged_at' => now(),
    ]);
    $release = Release::factory()->for($project)->shipped()->create(['version' => '1.0.0']);
    TicketRelease::factory()->create([
        'ticket_id' => $ticket->id,
        'release_id' => $release->id,
        'state' => TicketReleaseState::Shipped,
    ]);
    Environment::factory()->for($project)->create(['name' => 'production', 'current_version' => '1.0.0']);

    return compact('ticket', 'open', 'done');
}

function closingConditions(): array
{
    return [['key' => 'pull_request_merged'], ['key' => 'fix_deployed_there']];
}

test('a satisfied close policy moves the ticket to a closed status and records the audit', function (): void {
    ['ticket' => $ticket, 'done' => $done] = closure_workflow_fixture();
    $policy = ClosurePolicy::factory()->for($ticket->project)->closes()->create(['conditions' => closingConditions()]);

    $audit = app(ClosureApplicationService::class)->apply($ticket->refresh(), $policy, 'production');

    expect($audit)->not->toBeNull()
        ->and($audit->action)->toBe(ClosureAction::Close)
        ->and($ticket->fresh()->ticket_status_id)->toBe($done->id)
        ->and($audit->conditions_held['fix_deployed_there']['held'])->toBeTrue();
});

test('a satisfied propose policy records the audit without moving the ticket', function (): void {
    ['ticket' => $ticket, 'open' => $open] = closure_workflow_fixture();
    $policy = ClosurePolicy::factory()->for($ticket->project)->create([
        'action' => ClosureAction::Propose,
        'conditions' => closingConditions(),
    ]);

    $audit = app(ClosureApplicationService::class)->apply($ticket->refresh(), $policy, 'production');

    expect($audit)->not->toBeNull()
        ->and($audit->action)->toBe(ClosureAction::Propose)
        ->and($ticket->fresh()->ticket_status_id)->toBe($open->id);
});

test('a notify-only policy neither moves the ticket nor records an audit', function (): void {
    ['ticket' => $ticket, 'open' => $open] = closure_workflow_fixture();
    $policy = ClosurePolicy::factory()->for($ticket->project)->create([
        'action' => ClosureAction::NotifyOnly,
        'conditions' => closingConditions(),
    ]);

    $audit = app(ClosureApplicationService::class)->apply($ticket->refresh(), $policy, 'production');

    expect($audit)->toBeNull()
        ->and($ticket->fresh()->ticket_status_id)->toBe($open->id);
});

test('an unsatisfied policy does nothing', function (): void {
    ['ticket' => $ticket, 'open' => $open] = closure_workflow_fixture();
    // Reporting environment 'staging' has no deployment, so fix_deployed_there fails.
    $policy = ClosurePolicy::factory()->for($ticket->project)->closes()->create(['conditions' => closingConditions()]);

    $audit = app(ClosureApplicationService::class)->apply($ticket->refresh(), $policy, 'staging');

    expect($audit)->toBeNull()
        ->and($ticket->fresh()->ticket_status_id)->toBe($open->id);
});

test('a close policy with no path to a closed status is refused', function (): void {
    ['ticket' => $ticket] = closure_workflow_fixture(withClosedTransition: false);
    $policy = ClosurePolicy::factory()->for($ticket->project)->closes()->create(['conditions' => closingConditions()]);

    expect(fn (): ?\Modules\SAO\Models\ClosureAudit => app(ClosureApplicationService::class)->apply($ticket->refresh(), $policy, 'production'))
        ->toThrow(ClosureTransitionUnavailableException::class);
});
