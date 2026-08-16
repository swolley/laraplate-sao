<?php

declare(strict_types=1);

namespace Modules\SAO\Closure\Conditions;

use Modules\SAO\Closure\ClosureCondition;
use Modules\SAO\Closure\ClosureConditionResult;
use Modules\SAO\Closure\ClosureContext;

/**
 * Holds when the ticket has been marked resolved for at least the window, with
 * no counter-evidence since. An unresolved ticket never holds.
 */
final readonly class ResolvedForCondition implements ClosureCondition
{
    public function __construct(private int $days) {}

    public function key(): string
    {
        return 'resolved_for';
    }

    public function evaluate(ClosureContext $context): ClosureConditionResult
    {
        $threshold = $context->now->copy()->subDays($this->days);

        $evidence = [
            'days' => $this->days,
            'threshold' => $threshold->toIso8601String(),
            'resolved_at' => $context->resolved_at?->toIso8601String(),
        ];

        if ($context->resolved_at === null) {
            return ClosureConditionResult::failed($evidence);
        }

        return new ClosureConditionResult(
            $context->resolved_at->lessThanOrEqualTo($threshold),
            $evidence,
        );
    }
}
