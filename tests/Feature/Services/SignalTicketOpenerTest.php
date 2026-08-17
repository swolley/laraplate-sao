<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\SignalOpenOutcome;
use Modules\SAO\Enums\SignalState;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Signal;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;
use Modules\SAO\Models\WorkflowTransition;
use Modules\SAO\Services\SignalTicketOpener;

uses(RefreshDatabase::class);

/**
 * A project with a default ticket type whose workflow opens into an "open"
 * status — the minimum for TicketCreationService to open a ticket.
 */
function signalOpenerProject(bool $withDefaultType = true): Project
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

    if ($withDefaultType) {
        $project->ticketTypes()->attach($type->id, ['is_default' => true]);
    }

    return $project;
}

test('it opens a ticket from an open unlinked signal and links it back', function (): void {
    $project = signalOpenerProject();
    $signal = Signal::factory()->create(['project_id' => $project->id, 'group_key' => 'redis:timeout', 'occurrence_count' => 5]);

    $result = app(SignalTicketOpener::class)->open($signal);

    expect($result['outcome'])->toBe(SignalOpenOutcome::Opened)
        ->and($result['ticket'])->toBeInstanceOf(Ticket::class)
        ->and($result['ticket']->title)->toContain('redis:timeout')
        ->and($signal->refresh()->ticket_id)->toBe($result['ticket']->id)
        ->and(Ticket::query()->where('project_id', $project->id)->count())->toBe(1);
});

test('a second open is idempotent: the signal keeps its one ticket', function (): void {
    $project = signalOpenerProject();
    $signal = Signal::factory()->create(['project_id' => $project->id, 'occurrence_count' => 3]);

    $first = app(SignalTicketOpener::class)->open($signal);
    $second = app(SignalTicketOpener::class)->open($signal->refresh());

    expect($first['outcome'])->toBe(SignalOpenOutcome::Opened)
        ->and($second['outcome'])->toBe(SignalOpenOutcome::AlreadyLinked)
        ->and($second['ticket'])->toBeNull()
        ->and(Ticket::query()->where('project_id', $project->id)->count())->toBe(1);
});

test('it does not open when the project has no default ticket type', function (): void {
    $project = signalOpenerProject(withDefaultType: false);
    $signal = Signal::factory()->create(['project_id' => $project->id]);

    $result = app(SignalTicketOpener::class)->open($signal);

    expect($result['outcome'])->toBe(SignalOpenOutcome::NoDefaultType)
        ->and($signal->refresh()->ticket_id)->toBeNull()
        ->and(Ticket::query()->count())->toBe(0);
});

test('it does not open for an inactive project', function (): void {
    $project = signalOpenerProject();
    $project->update(['is_active' => false]);
    $signal = Signal::factory()->create(['project_id' => $project->id]);

    $result = app(SignalTicketOpener::class)->open($signal);

    expect($result['outcome'])->toBe(SignalOpenOutcome::ProjectUnavailable)
        ->and(Ticket::query()->count())->toBe(0);
});

test('the command opens eligible signals above the threshold and skips the rest', function (): void {
    config()->set('sao.signals.auto_open.min_occurrences', 3);

    $project = signalOpenerProject();
    $eligible = Signal::factory()->create(['project_id' => $project->id, 'group_key' => 'eligible', 'occurrence_count' => 4]);
    $belowThreshold = Signal::factory()->create(['project_id' => $project->id, 'group_key' => 'quiet', 'occurrence_count' => 1]);
    $resolved = Signal::factory()->create(['project_id' => $project->id, 'group_key' => 'resolved', 'occurrence_count' => 9, 'state' => SignalState::Resolved]);

    $this->artisan('sao:signals:auto-open')->assertSuccessful();

    expect($eligible->refresh()->ticket_id)->not->toBeNull()
        ->and($belowThreshold->refresh()->ticket_id)->toBeNull()
        ->and($resolved->refresh()->ticket_id)->toBeNull()
        ->and(Ticket::query()->where('project_id', $project->id)->count())->toBe(1);
});
