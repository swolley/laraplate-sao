<?php

declare(strict_types=1);

namespace Modules\SAO\Closure;

use Modules\SAO\Models\ClosurePolicy;

/**
 * Evaluates a policy's conditions against a context and reports the decision.
 * Conditions combine with AND: the decision is satisfied only when every one
 * holds. Evaluation is total — every condition runs so its evidence is recorded
 * even when an earlier one already failed.
 */
final class ClosureEvaluator
{
    public function __construct(private readonly ClosureConditionRegistry $registry) {}

    public function evaluate(ClosurePolicy $policy, ClosureContext $context): ClosureDecision
    {
        $conditions = $this->registry->build($policy->conditions);

        $outcomes = [];
        $satisfied = $conditions !== [];

        foreach ($conditions as $condition) {
            $result = $condition->evaluate($context);
            $outcomes[$condition->key()] = $result;
            $satisfied = $satisfied && $result->held;
        }

        return new ClosureDecision($policy->action, $satisfied, $outcomes);
    }
}
