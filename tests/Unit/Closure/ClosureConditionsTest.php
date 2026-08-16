<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\SAO\Closure\ClosureConditionRegistry;
use Modules\SAO\Closure\ClosureContext;
use Modules\SAO\Closure\Conditions\FixDeployedThereCondition;
use Modules\SAO\Closure\Conditions\FixReleasedCondition;
use Modules\SAO\Closure\Conditions\InternalTicketsOnlyCondition;
use Modules\SAO\Closure\Conditions\NoRecurrenceForCondition;
use Modules\SAO\Closure\Conditions\PullRequestMergedCondition;
use Modules\SAO\Closure\Conditions\ResolvedForCondition;
use Modules\SAO\Exceptions\UnknownClosureConditionException;

function closureContext(array $overrides = []): ClosureContext
{
    $now = CarbonImmutable::parse('2026-08-16 12:00:00');

    return new ClosureContext(
        pull_request_merged: $overrides['pull_request_merged'] ?? false,
        reporting_environment: $overrides['reporting_environment'] ?? 'production',
        last_recurrence_at: $overrides['last_recurrence_at'] ?? null,
        fix_released: $overrides['fix_released'] ?? false,
        fix_deployed_there: $overrides['fix_deployed_there'] ?? false,
        resolved_at: $overrides['resolved_at'] ?? null,
        is_internal: $overrides['is_internal'] ?? false,
        now: $overrides['now'] ?? $now,
    );
}

test('pull request merged holds only when merged', function (): void {
    $condition = new PullRequestMergedCondition();

    expect($condition->evaluate(closureContext(['pull_request_merged' => true]))->held)->toBeTrue()
        ->and($condition->evaluate(closureContext(['pull_request_merged' => false]))->held)->toBeFalse();
});

test('no recurrence holds when never recurred or older than the window', function (): void {
    $condition = new NoRecurrenceForCondition(7);
    $now = CarbonImmutable::parse('2026-08-16 12:00:00');

    expect($condition->evaluate(closureContext(['last_recurrence_at' => null]))->held)->toBeTrue()
        ->and($condition->evaluate(closureContext(['last_recurrence_at' => $now->subDays(8)]))->held)->toBeTrue()
        ->and($condition->evaluate(closureContext(['last_recurrence_at' => $now->subDays(3)]))->held)->toBeFalse();
});

test('fix released reflects the shipped flag', function (): void {
    $condition = new FixReleasedCondition();

    expect($condition->evaluate(closureContext(['fix_released' => true]))->held)->toBeTrue()
        ->and($condition->evaluate(closureContext(['fix_released' => false]))->held)->toBeFalse();
});

test('fix deployed there reflects the deployment flag', function (): void {
    $condition = new FixDeployedThereCondition();

    expect($condition->evaluate(closureContext(['fix_deployed_there' => true]))->held)->toBeTrue()
        ->and($condition->evaluate(closureContext(['fix_deployed_there' => false]))->held)->toBeFalse();
});

test('resolved for holds only past the window and never when unresolved', function (): void {
    $condition = new ResolvedForCondition(3);
    $now = CarbonImmutable::parse('2026-08-16 12:00:00');

    expect($condition->evaluate(closureContext(['resolved_at' => null]))->held)->toBeFalse()
        ->and($condition->evaluate(closureContext(['resolved_at' => $now->subDays(5)]))->held)->toBeTrue()
        ->and($condition->evaluate(closureContext(['resolved_at' => $now->subDay()]))->held)->toBeFalse();
});

test('internal tickets only holds for internal tickets', function (): void {
    $condition = new InternalTicketsOnlyCondition();

    expect($condition->evaluate(closureContext(['is_internal' => true]))->held)->toBeTrue()
        ->and($condition->evaluate(closureContext(['is_internal' => false]))->held)->toBeFalse();
});

test('the registry builds conditions from definitions and rejects unknown keys', function (): void {
    $registry = new ClosureConditionRegistry();

    $conditions = $registry->build([
        ['key' => 'pull_request_merged'],
        ['key' => 'no_recurrence_for', 'config' => ['days' => 14]],
    ]);

    expect($conditions)->toHaveCount(2)
        ->and($conditions[0])->toBeInstanceOf(PullRequestMergedCondition::class)
        ->and($conditions[1])->toBeInstanceOf(NoRecurrenceForCondition::class);

    expect(fn () => $registry->make('nonsense'))->toThrow(UnknownClosureConditionException::class);
});
