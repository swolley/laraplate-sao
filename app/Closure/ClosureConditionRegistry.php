<?php

declare(strict_types=1);

namespace Modules\SAO\Closure;

use Modules\SAO\Closure\Conditions\FixDeployedThereCondition;
use Modules\SAO\Closure\Conditions\FixReleasedCondition;
use Modules\SAO\Closure\Conditions\InternalTicketsOnlyCondition;
use Modules\SAO\Closure\Conditions\NoRecurrenceForCondition;
use Modules\SAO\Closure\Conditions\PullRequestMergedCondition;
use Modules\SAO\Closure\Conditions\ResolvedForCondition;
use Modules\SAO\Exceptions\UnknownClosureConditionException;

/**
 * Builds closure conditions from a policy's stored `{key, config}` definitions.
 * Keeping construction here means a policy is plain data and the set of known
 * conditions is one closed, testable list.
 */
final class ClosureConditionRegistry
{
    /**
     * Build one condition from its key and config.
     *
     * @param  array<string, mixed>  $config
     */
    public function make(string $key, array $config = []): ClosureCondition
    {
        return match ($key) {
            'pull_request_merged' => new PullRequestMergedCondition(),
            'fix_released' => new FixReleasedCondition(),
            'fix_deployed_there' => new FixDeployedThereCondition(),
            'internal_tickets_only' => new InternalTicketsOnlyCondition(),
            'no_recurrence_for' => new NoRecurrenceForCondition((int) ($config['days'] ?? 0)),
            'resolved_for' => new ResolvedForCondition((int) ($config['days'] ?? 0)),
            default => throw UnknownClosureConditionException::for($key),
        };
    }

    /**
     * Build the ordered list of conditions from a policy's json definitions.
     *
     * @param  list<array{key: string, config?: array<string, mixed>}>  $definitions
     * @return list<ClosureCondition>
     */
    public function build(array $definitions): array
    {
        return array_map(
            fn (array $definition): ClosureCondition => $this->make(
                $definition['key'],
                $definition['config'] ?? [],
            ),
            $definitions,
        );
    }
}
