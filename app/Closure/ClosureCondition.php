<?php

declare(strict_types=1);

namespace Modules\SAO\Closure;

/**
 * One independently testable predicate over verifiable facts. A closure policy
 * combines several with AND; each decides purely from the {@see ClosureContext}
 * so no condition can depend on a model's judgement (D8).
 */
interface ClosureCondition
{
    /**
     * The stable key identifying the condition in a policy's json.
     */
    public function key(): string;

    public function evaluate(ClosureContext $context): ClosureConditionResult;
}
