<?php

declare(strict_types=1);

namespace Modules\SAO\Closure\Conditions;

use Modules\SAO\Closure\ClosureCondition;
use Modules\SAO\Closure\ClosureConditionResult;
use Modules\SAO\Closure\ClosureContext;

/**
 * Holds when a merged pull request is linked to the ticket.
 */
final class PullRequestMergedCondition implements ClosureCondition
{
    public function key(): string
    {
        return 'pull_request_merged';
    }

    public function evaluate(ClosureContext $context): ClosureConditionResult
    {
        $evidence = ['pull_request_merged' => $context->pull_request_merged];

        return new ClosureConditionResult($context->pull_request_merged, $evidence);
    }
}
