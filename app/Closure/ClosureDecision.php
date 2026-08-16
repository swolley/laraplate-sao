<?php

declare(strict_types=1);

namespace Modules\SAO\Closure;

use Modules\SAO\Enums\ClosureAction;

/**
 * The result of evaluating a policy against a context: the action to take,
 * whether every condition held (AND semantics), and the per-condition outcomes
 * that decided it. The outcomes are the auditable "closed because".
 */
final readonly class ClosureDecision
{
    /**
     * @param  array<string, ClosureConditionResult>  $outcomes
     */
    public function __construct(
        public ClosureAction $action,
        public bool $satisfied,
        public array $outcomes,
    ) {}

    /**
     * The outcomes flattened to `{key: {held, evidence}}`, suitable for storing
     * as a `ClosureAudit`'s json.
     *
     * @return array<string, array{held: bool, evidence: array<string, mixed>}>
     */
    public function toEvidence(): array
    {
        return array_map(
            static fn (ClosureConditionResult $result): array => [
                'held' => $result->held,
                'evidence' => $result->evidence,
            ],
            $this->outcomes,
        );
    }
}
