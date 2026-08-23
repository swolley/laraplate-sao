<?php

declare(strict_types=1);

namespace Modules\SAO\Attribution;

use Modules\SAO\Models\ChangeRef;

/**
 * The result of writing one {@see CodeReference}: the change refs that were
 * created or upgraded, and any ticket keys that resolved to no ticket. The
 * affected tickets (reachable from the change refs) are the seam the closure
 * pipeline re-evaluates.
 */
final readonly class CodeReferenceOutcome
{
    /**
     * @param  list<ChangeRef>  $changeRefs
     * @param  list<string>  $unknownKeys
     */
    public function __construct(
        public array $changeRefs,
        public array $unknownKeys,
    ) {}
}
