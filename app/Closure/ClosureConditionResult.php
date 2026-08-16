<?php

declare(strict_types=1);

namespace Modules\SAO\Closure;

/**
 * The outcome of evaluating one closure condition: whether it held and the
 * verifiable evidence that decided it. The evidence is what a `ClosureAudit`
 * stores as the "closed because".
 */
final readonly class ClosureConditionResult
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public bool $held,
        public array $evidence = [],
    ) {}

    /**
     * @param  array<string, mixed>  $evidence
     */
    public static function held(array $evidence = []): self
    {
        return new self(true, $evidence);
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    public static function failed(array $evidence = []): self
    {
        return new self(false, $evidence);
    }
}
