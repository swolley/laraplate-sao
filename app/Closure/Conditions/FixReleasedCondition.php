<?php

declare(strict_types=1);

namespace Modules\SAO\Closure\Conditions;

use Modules\SAO\Closure\ClosureCondition;
use Modules\SAO\Closure\ClosureConditionResult;
use Modules\SAO\Closure\ClosureContext;

/**
 * Holds when a shipped release carrying the fix exists. A candidate never
 * satisfies this: a fix present only in a release candidate is on staging, not
 * in anyone's production (§9.1). The caller sets `fix_released` from shipped
 * releases only.
 */
final class FixReleasedCondition implements ClosureCondition
{
    public function key(): string
    {
        return 'fix_released';
    }

    public function evaluate(ClosureContext $context): ClosureConditionResult
    {
        return new ClosureConditionResult(
            $context->fix_released,
            ['fix_released' => $context->fix_released],
        );
    }
}
