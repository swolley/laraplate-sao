<?php

declare(strict_types=1);

namespace Modules\SAO\Data;

use Modules\SAO\Enums\OwnershipRule;

/**
 * One candidate's claim to ownership of a ticket's change, produced by a single
 * deterministic rule. It is assembled elsewhere from code evidence (CODEOWNERS,
 * blame, recent touches, path prefixes) and fed to the suggester as plain data,
 * so the ranking that picks a winner stays a pure, testable function.
 */
final readonly class OwnershipEvidence
{
    /**
     * @param  list<string>  $paths  The files that produced the claim.
     * @param  array<string, mixed>  $detail  Rule-specific supporting data (e.g. commit shas).
     */
    public function __construct(
        public int $userId,
        public OwnershipRule $rule,
        public float $score,
        public array $paths = [],
        public array $detail = [],
    ) {}
}
