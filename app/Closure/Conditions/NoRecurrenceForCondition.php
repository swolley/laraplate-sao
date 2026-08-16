<?php

declare(strict_types=1);

namespace Modules\SAO\Closure\Conditions;

use Modules\SAO\Closure\ClosureCondition;
use Modules\SAO\Closure\ClosureConditionResult;
use Modules\SAO\Closure\ClosureContext;

/**
 * Holds when the signal has not recurred in the reporting environment within
 * the trailing window. Never-recurred also holds. "Silence is not evidence"
 * unless the source was alive — the caller assembles `last_recurrence_at` only
 * from occurrences in the reporting environment, so silence here is scoped.
 */
final readonly class NoRecurrenceForCondition implements ClosureCondition
{
    public function __construct(private int $days) {}

    public function key(): string
    {
        return 'no_recurrence_for';
    }

    public function evaluate(ClosureContext $context): ClosureConditionResult
    {
        $threshold = $context->now->copy()->subDays($this->days);

        $evidence = [
            'days' => $this->days,
            'threshold' => $threshold->toIso8601String(),
            'last_recurrence_at' => $context->last_recurrence_at?->toIso8601String(),
            'reporting_environment' => $context->reporting_environment,
        ];

        if ($context->last_recurrence_at === null) {
            return ClosureConditionResult::held($evidence);
        }

        return new ClosureConditionResult(
            $context->last_recurrence_at->lessThan($threshold),
            $evidence,
        );
    }
}
