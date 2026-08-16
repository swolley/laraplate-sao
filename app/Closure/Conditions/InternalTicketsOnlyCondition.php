<?php

declare(strict_types=1);

namespace Modules\SAO\Closure\Conditions;

use Modules\SAO\Closure\ClosureCondition;
use Modules\SAO\Closure\ClosureConditionResult;
use Modules\SAO\Closure\ClosureContext;

/**
 * Restricts a policy to internal tickets — those with no external `TicketLink`.
 * On a ticket a foreign tracker owns, it never holds, so the policy declines to
 * act where SAO is not authoritative.
 */
final class InternalTicketsOnlyCondition implements ClosureCondition
{
    public function key(): string
    {
        return 'internal_tickets_only';
    }

    public function evaluate(ClosureContext $context): ClosureConditionResult
    {
        return new ClosureConditionResult(
            $context->is_internal,
            ['is_internal' => $context->is_internal],
        );
    }
}
