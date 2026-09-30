<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\SyncDirection;
use Modules\SAO\Enums\SyncOutcome;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\ProjectBinding;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketLink;
use Modules\SAO\Services\IssueSyncService;
use Modules\SAO\Tests\Support\Drivers\FakeRedmineApi;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->redmine = FakeRedmineApi::install();

    [$this->project, $this->type] = sync_fixture();

    $this->connection = Connection::factory()->create([
        'driver_key' => 'redmine',
        'base_url' => 'https://redmine.example.test',
        'capabilities' => [Capability::Issues],
        'credential' => ['token' => 'secret-key'],
    ]);

    $this->bindRedmine = function (SyncDirection $direction, array $statusMap = []): ProjectBinding {
        return ProjectBinding::factory()->create([
            'project_id' => $this->project->id,
            'connection_id' => $this->connection->id,
            'capability' => Capability::Issues,
            'remote_identifier' => 'demo-project',
            'sync_direction' => $direction,
            'status_map' => $statusMap,
            'config' => ['ticket_type' => $this->type->id],
        ]);
    };
});

test('an outbound push creates exactly one redmine issue and a retry writes nothing', function (): void {
    /** @var ProjectBinding $binding */
    $binding = ($this->bindRedmine)(SyncDirection::Outbound);

    /** @var Project $project */
    $project = $this->project;
    $ticket = Ticket::factory()->forProject($project)->create(['title' => 'Checkout fails', 'description' => 'Stack trace attached']);

    $service = app(IssueSyncService::class);

    expect($service->push($binding, $ticket))->toBe(SyncOutcome::Created)
        ->and($service->push($binding, $ticket))->toBe(SyncOutcome::SkippedIdempotent)
        ->and($this->redmine->createCount)->toBe(1)
        ->and($this->redmine->updateCount)->toBe(0)
        ->and($this->redmine->issues)->toHaveCount(1);

    $issue = array_values($this->redmine->issues)[0];
    $link = TicketLink::query()->where('ticket_id', $ticket->id)->sole();

    expect($issue['subject'])->toBe('Checkout fails')
        ->and($issue['description'])->toBe('Stack trace attached')
        ->and($link->connection_id)->toBe($this->connection->id)
        ->and($link->remote_id)->toBe((string) $issue['id'])
        ->and($link->url)->toBe("https://redmine.example.test/issues/{$issue['id']}");

    Http::assertSentCount(1);
    Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
        && $request->hasHeader('X-Redmine-API-Key', 'secret-key')
        && ($request->data()['issue']['project_id'] ?? null) === 'demo-project');
});

test('a changed ticket pushes an update to the linked redmine issue, never a second create', function (): void {
    /** @var ProjectBinding $binding */
    $binding = ($this->bindRedmine)(SyncDirection::Bidirectional);

    /** @var Project $project */
    $project = $this->project;
    $ticket = Ticket::factory()->forProject($project)->create(['title' => 'Before']);

    $service = app(IssueSyncService::class);
    $service->push($binding, $ticket);

    $ticket->update(['title' => 'After']);

    expect($service->push($binding, $ticket->refresh()))->toBe(SyncOutcome::Updated)
        ->and($this->redmine->createCount)->toBe(1)
        ->and($this->redmine->updateCount)->toBe(1)
        ->and(array_values($this->redmine->issues)[0]['subject'])->toBe('After')
        ->and(TicketLink::query()->where('ticket_id', $ticket->id)->count())->toBe(1);
});

test('an inbound pull through the redmine binding creates a linked SAO ticket', function (): void {
    /** @var ProjectBinding $binding */
    $binding = ($this->bindRedmine)(SyncDirection::Inbound, ['New' => 'open']);
    $remoteId = $this->redmine->seed('Reported upstream', 'New', 'From Redmine');

    $service = app(IssueSyncService::class);

    expect($service->pull($binding, (string) $remoteId))->toBe(SyncOutcome::Created)
        ->and($service->pull($binding, (string) $remoteId))->toBe(SyncOutcome::Updated)
        ->and($this->redmine->createCount)->toBe(0);

    $link = TicketLink::query()->where('connection_id', $this->connection->id)->where('remote_id', (string) $remoteId)->sole();

    expect($link->ticket->title)->toBe('Reported upstream')
        ->and($link->ticket->project_id)->toBe($this->project->id);
});

test('the binding direction gates the redmine sync', function (SyncDirection $direction): void {
    /** @var ProjectBinding $binding */
    $binding = ($this->bindRedmine)($direction, ['New' => 'open']);

    /** @var Project $project */
    $project = $this->project;
    $ticket = Ticket::factory()->forProject($project)->create();
    $remoteId = $this->redmine->seed('Remote', 'New');

    $service = app(IssueSyncService::class);

    expect($service->push($binding, $ticket))->toBe($direction->syncsOutbound() ? SyncOutcome::Created : SyncOutcome::SkippedDirection)
        ->and($service->pull($binding, (string) $remoteId))->toBe($direction->syncsInbound() ? SyncOutcome::Created : SyncOutcome::SkippedDirection);
})->with([
    'inbound' => [SyncDirection::Inbound],
    'outbound' => [SyncDirection::Outbound],
    'disabled' => [SyncDirection::Disabled],
]);
