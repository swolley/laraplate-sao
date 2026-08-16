<?php

declare(strict_types=1);

namespace Modules\SAO\Closure\Conditions;

use Modules\SAO\Closure\ClosureCondition;
use Modules\SAO\Closure\ClosureConditionResult;
use Modules\SAO\Closure\ClosureContext;

/**
 * Holds when the shipped release carrying the fix is the version currently
 * running on the reporting environment — the fix is not merely released but
 * actually deployed where the bug was reported.
 */
final class FixDeployedThereCondition implements ClosureCondition
{
    public function key(): string
    {
        return 'fix_deployed_there';
    }

    public function evaluate(ClosureContext $context): ClosureConditionResult
    {
        return new ClosureConditionResult(
            $context->fix_deployed_there,
            [
                'fix_deployed_there' => $context->fix_deployed_there,
                'reporting_environment' => $context->reporting_environment,
            ],
        );
    }
}
