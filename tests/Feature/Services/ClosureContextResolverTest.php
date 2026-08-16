<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\ChangeRefType;
use Modules\SAO\Enums\ClosureAction;
use Modules\SAO\Enums\TicketReleaseState;
use Modules\SAO\Models\ChangeRef;
use Modules\SAO\Models\ClosurePolicy;
use Modules\SAO\Models\Environment;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\Signal;
use Modules\SAO\Models\SignalOccurrence;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketRelease;
use Modules\SAO\Services\ClosureContextResolver;
use Modules\SAO\Services\FixStatusResolver;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->resolver = new ClosureContextResolver(new FixStatusResolver());
});

test('a closure policy round-trips its conditions json and action', function (): void {
    $policy = ClosurePolicy::factory()->closes()->create([
        'conditions' => [
            ['key' => 'pull_request_merged'],
            ['key' => 'no_recurrence_for', 'config' => ['days' => 30]],
        ],
    ]);

    $fresh = $policy->fresh();

    expect($fresh->action)->toBe(ClosureAction::Close)
        ->and($fresh->conditions)->toBe([
            ['key' => 'pull_request_merged'],
            ['key' => 'no_recurrence_for', 'config' => ['days' => 30]],
        ]);
});

test('the resolver assembles a context from a ticket world', function (): void {
    $now = CarbonImmutable::parse('2026-08-16 12:00:00');
    $project = Project::factory()->create();
    $ticket = Ticket::factory()->for($project)->create();

    ChangeRef::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ChangeRefType::PullRequest,
        'identifier' => '11',
        'merged_at' => $now->subDays(20),
    ]);

    $release = Release::factory()->for($project)->shipped()->create(['version' => '1.4.0']);
    TicketRelease::factory()->create([
        'ticket_id' => $ticket->id,
        'release_id' => $release->id,
        'state' => TicketReleaseState::Shipped,
    ]);
    Environment::factory()->for($project)->create(['name' => 'production', 'current_version' => '1.4.0']);

    $signal = Signal::factory()->for($project)->create(['ticket_id' => $ticket->id]);
    SignalOccurrence::factory()->for($signal)->create(['environment' => 'production', 'occurred_at' => $now->subDays(10)]);
    SignalOccurrence::factory()->for($signal)->create(['environment' => 'staging', 'occurred_at' => $now->subDay()]);

    $context = $this->resolver->forTicket($ticket->refresh(), 'production', $now);

    expect($context->pull_request_merged)->toBeTrue()
        ->and($context->fix_released)->toBeTrue()
        ->and($context->fix_deployed_there)->toBeTrue()
        ->and($context->is_internal)->toBeTrue()
        ->and($context->reporting_environment)->toBe('production')
        ->and($context->last_recurrence_at?->toDateString())->toBe($now->subDays(10)->toDateString());
});

test('recurrence is scoped to the reporting environment', function (): void {
    $now = CarbonImmutable::parse('2026-08-16 12:00:00');
    $ticket = Ticket::factory()->create();
    $signal = Signal::factory()->create(['ticket_id' => $ticket->id]);
    SignalOccurrence::factory()->for($signal)->create(['environment' => 'staging', 'occurred_at' => $now->subHour()]);

    $context = $this->resolver->forTicket($ticket->refresh(), 'production', $now);

    expect($context->last_recurrence_at)->toBeNull();
});
