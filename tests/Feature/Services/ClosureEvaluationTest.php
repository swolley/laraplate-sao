<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Closure\ClosureConditionRegistry;
use Modules\SAO\Closure\ClosureContext;
use Modules\SAO\Closure\ClosureEvaluator;
use Modules\SAO\Enums\ClosureAction;
use Modules\SAO\Models\ClosurePolicy;
use Modules\SAO\Models\SignalOccurrence;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\ClosureAuditService;

uses(RefreshDatabase::class);

function evaluationContext(array $overrides = []): ClosureContext
{
    return new ClosureContext(
        pull_request_merged: $overrides['pull_request_merged'] ?? true,
        reporting_environment: 'production',
        last_recurrence_at: $overrides['last_recurrence_at'] ?? null,
        fix_released: $overrides['fix_released'] ?? true,
        fix_deployed_there: $overrides['fix_deployed_there'] ?? true,
        resolved_at: $overrides['resolved_at'] ?? null,
        is_internal: $overrides['is_internal'] ?? true,
        now: CarbonImmutable::parse('2026-08-16 12:00:00'),
    );
}

beforeEach(function (): void {
    $this->evaluator = new ClosureEvaluator(new ClosureConditionRegistry());
    $this->audits = new ClosureAuditService();
});

test('a policy is satisfied only when every condition holds', function (): void {
    $policy = ClosurePolicy::factory()->closes()->create([
        'conditions' => [
            ['key' => 'pull_request_merged'],
            ['key' => 'fix_deployed_there'],
        ],
    ]);

    $satisfied = $this->evaluator->evaluate($policy, evaluationContext());
    $notSatisfied = $this->evaluator->evaluate($policy, evaluationContext(['fix_deployed_there' => false]));

    expect($satisfied->satisfied)->toBeTrue()
        ->and($satisfied->action)->toBe(ClosureAction::Close)
        ->and($notSatisfied->satisfied)->toBeFalse()
        ->and($notSatisfied->outcomes)->toHaveKeys(['pull_request_merged', 'fix_deployed_there'])
        ->and($notSatisfied->outcomes['fix_deployed_there']->held)->toBeFalse();
});

test('an empty condition set is never satisfied', function (): void {
    $policy = ClosurePolicy::factory()->create(['conditions' => []]);

    expect($this->evaluator->evaluate($policy, evaluationContext())->satisfied)->toBeFalse();
});

test('recording a closure stores which conditions held', function (): void {
    $ticket = Ticket::factory()->create();
    $policy = ClosurePolicy::factory()->for($ticket->project)->closes()->create([
        'conditions' => [['key' => 'pull_request_merged'], ['key' => 'fix_deployed_there']],
    ]);
    $decision = $this->evaluator->evaluate($policy, evaluationContext());

    $audit = $this->audits->record($ticket, $decision, 'production', $policy);

    expect($audit->action)->toBe(ClosureAction::Close)
        ->and($audit->is_premature)->toBeFalse()
        ->and($audit->conditions_held['pull_request_merged']['held'])->toBeTrue()
        ->and($audit->reporting_environment)->toBe('production');
});

test('a recurrence reopens the closure and marks it premature with returned-after', function (): void {
    $ticket = Ticket::factory()->create();
    $audit = \Modules\SAO\Models\ClosureAudit::factory()->create([
        'ticket_id' => $ticket->id,
        'closed_at' => CarbonImmutable::parse('2026-08-01 00:00:00'),
    ]);
    $occurrence = SignalOccurrence::factory()->create([
        'environment' => 'production',
        'occurred_at' => CarbonImmutable::parse('2026-08-04 00:00:00'),
    ]);

    $reopened = $this->audits->reopenForRecurrence($audit, $occurrence);

    expect($reopened->is_premature)->toBeTrue()
        ->and($reopened->returned_occurrence_id)->toBe($occurrence->id)
        ->and($reopened->returned_after_seconds)->toBe(3 * 24 * 60 * 60)
        ->and($reopened->reopened_at->toDateString())->toBe('2026-08-04');
});
