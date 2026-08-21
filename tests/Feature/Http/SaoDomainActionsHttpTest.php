<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\ChangeRefType;
use Modules\SAO\Enums\ConnectionHealth;
use Modules\SAO\Enums\IngestStatus;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Enums\TicketReleaseState;
use Modules\SAO\Models\ChangeRef;
use Modules\SAO\Models\ClosurePolicy;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\Environment;
use Modules\SAO\Models\IngestEvent;
use Modules\SAO\Models\OwnershipSuggestion;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\SourceProfile;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketRelease;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;
use Modules\SAO\Models\WorkflowTransition;

uses(RefreshDatabase::class);

function saoDomainActionUrl(string $action, string $entity): string
{
    return route('core.crud.domain-action', ['action' => $action, 'module' => 'sao', 'entity' => $entity]);
}

/**
 * A user holding exactly the named domain permission. The permission is created
 * against the record's own connection so it matches the `forModel` name the
 * policy checks, mirroring the ERP domain-action HTTP test.
 */
function saoUserWith(string $permission): User
{
    Permission::findOrCreate($permission, 'web');

    $user = User::factory()->create();
    $user->givePermissionTo($permission);

    return $user;
}

/**
 * A project whose scheme is (creation) -> Open -> Doing, plus a `Blocked` status
 * that no transition reaches.
 *
 * @return array{ticket: Ticket, open: TicketStatus, doing: TicketStatus, blocked: TicketStatus}
 */
function saoTransitionFixture(): array
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

    $ticket = Ticket::factory()->forProject($project)->create([
        'ticket_type_id' => $type->id,
        'ticket_status_id' => $open->id,
    ]);

    return compact('ticket', 'open', 'doing', 'blocked');
}

/**
 * A ticket whose closure conditions (merged PR, fix deployed on production) hold,
 * with a permission-free transition to a closed `Done` status.
 *
 * @return array{ticket: Ticket, done: TicketStatus}
 */
function saoClosureFixture(): array
{
    $open = TicketStatus::factory()->category(StatusCategory::Open)->create(['name' => 'Open']);
    $done = TicketStatus::factory()->category(StatusCategory::Closed)->create(['name' => 'Done']);

    $scheme = WorkflowScheme::factory()->create(['name' => 'Closable']);
    WorkflowTransition::factory()->for($scheme, 'scheme')->create([
        'from_status_id' => null,
        'to_status_id' => $open->id,
        'label' => 'Open',
    ]);
    WorkflowTransition::factory()->for($scheme, 'scheme')->create([
        'from_status_id' => $open->id,
        'to_status_id' => $done->id,
        'label' => 'Close',
    ]);

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

    return compact('ticket', 'done');
}

test('transition moves a ticket through a declared transition', function (): void {
    ['ticket' => $ticket, 'doing' => $doing] = saoTransitionFixture();
    $user = saoUserWith(PermissionName::forModel($ticket, 'transition'));

    $this->actingAs($user)
        ->postJson(saoDomainActionUrl('transition', 'tickets'), [
            'id' => $ticket->id,
            'to_status_id' => $doing->id,
        ])
        ->assertOk();

    expect($ticket->fresh()->ticket_status_id)->toBe($doing->id);
});

test('transition to an undeclared status is rejected and leaves the ticket untouched', function (): void {
    ['ticket' => $ticket, 'open' => $open, 'blocked' => $blocked] = saoTransitionFixture();
    $user = saoUserWith(PermissionName::forModel($ticket, 'transition'));

    $this->actingAs($user)
        ->postJson(saoDomainActionUrl('transition', 'tickets'), [
            'id' => $ticket->id,
            'to_status_id' => $blocked->id,
        ])
        ->assertStatus(500);

    expect($ticket->fresh()->ticket_status_id)->toBe($open->id);
});

test('transitions lists the moves a ticket may make from its current status', function (): void {
    ['ticket' => $ticket, 'doing' => $doing] = saoTransitionFixture();
    $user = saoUserWith(PermissionName::forModel($ticket, 'transition'));

    $this->actingAs($user)
        ->postJson(saoDomainActionUrl('transitions', 'tickets'), ['id' => $ticket->id])
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.to_status_id', $doing->id)
        ->assertJsonPath('data.0.label', 'Doing')
        ->assertJsonPath('data.0.allowed', true);
});

test('close applies a satisfied closure policy and moves the ticket to a closed status', function (): void {
    ['ticket' => $ticket, 'done' => $done] = saoClosureFixture();
    $policy = ClosurePolicy::factory()->for($ticket->project)->closes()->create([
        'conditions' => [['key' => 'pull_request_merged'], ['key' => 'fix_deployed_there']],
    ]);
    $user = saoUserWith(PermissionName::forModel($ticket, 'close'));

    $this->actingAs($user)
        ->postJson(saoDomainActionUrl('close', 'tickets'), [
            'id' => $ticket->id,
            'policy_id' => $policy->id,
            'reporting_environment' => 'production',
        ])
        ->assertOk();

    expect($ticket->fresh()->ticket_status_id)->toBe($done->id);
});

test('accept applies an ownership suggestion and assigns the suggested user', function (): void {
    $suggestion = OwnershipSuggestion::factory()->create();
    $user = saoUserWith(PermissionName::forModel($suggestion, 'accept'));

    $this->actingAs($user)
        ->postJson(saoDomainActionUrl('accept', 'ownership_suggestions'), ['id' => $suggestion->id])
        ->assertOk();

    expect($suggestion->ticket->fresh()->assignee_id)->toBe($suggestion->suggested_user_id);
});

test('health probes a connection and records the outcome', function (): void {
    Http::fake(['*/rate_limit' => Http::response(['resources' => []], 200)]);
    $connection = Connection::factory()->create([
        'name' => 'Acme GitHub',
        'driver_key' => 'github',
        'base_url' => 'https://api.github.com',
        'credential' => ['token' => 'ghp_secret'],
        'capabilities' => [Capability::Issues],
    ]);
    $user = saoUserWith(PermissionName::forModel($connection, 'health'));

    $this->actingAs($user)
        ->postJson(saoDomainActionUrl('health', 'connections'), ['id' => $connection->id])
        ->assertOk()
        ->assertJsonPath('data.healthy', true);

    expect($connection->fresh()->health_state)->toBe(ConnectionHealth::Healthy);
});

test('replay dry-runs an ingest event against its recorded profile', function (): void {
    $profile = SourceProfile::factory()->create([
        'name' => 'glitchtip',
        'matchers' => [['path' => 'source', 'operator' => 'equals', 'value' => 'glitchtip']],
        'field_bindings' => ['message' => 'error.message', 'class' => 'error.type'],
    ]);
    $event = IngestEvent::factory()->create([
        'delivery_id' => 'del-' . uniqid(),
        'payload' => ['source' => 'glitchtip', 'error' => ['message' => 'boom', 'type' => 'RuntimeException']],
        'source_profile_id' => $profile->id,
        'status' => IngestStatus::Received,
    ]);
    $user = saoUserWith(PermissionName::forModel($event, 'replay'));

    $this->actingAs($user)
        ->postJson(saoDomainActionUrl('replay', 'ingest_events'), ['id' => $event->id])
        ->assertOk()
        ->assertJsonPath('data.matches', true);

    // A dry-run writes nothing: the event keeps its received status.
    expect($event->fresh()->status)->toBe(IngestStatus::Received);
});

test('a user without the permission is refused', function (): void {
    ['ticket' => $ticket, 'doing' => $doing] = saoTransitionFixture();

    $this->actingAs(User::factory()->create())
        ->postJson(saoDomainActionUrl('transition', 'tickets'), [
            'id' => $ticket->id,
            'to_status_id' => $doing->id,
        ])
        ->assertUnauthorized();

    expect($ticket->fresh()->ticket_status_id)->toBe($ticket->ticket_status_id);
});

test('an unregistered action returns 404', function (): void {
    $ticket = Ticket::factory()->create();
    $user = saoUserWith(PermissionName::forModel($ticket, 'transition'));

    $this->actingAs($user)
        ->postJson(saoDomainActionUrl('teleport', 'tickets'), ['id' => $ticket->id])
        ->assertNotFound();
});
