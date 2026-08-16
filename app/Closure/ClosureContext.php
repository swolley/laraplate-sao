<?php

declare(strict_types=1);

namespace Modules\SAO\Closure;

use Carbon\CarbonInterface;

/**
 * The assembled, verifiable facts a closure policy is evaluated against. It is
 * a plain value object so conditions are pure functions of it — no Eloquent, no
 * clock reads. `now` is injected so a policy's outcome is deterministic and
 * reproducible in a test and in a replay alike.
 */
final readonly class ClosureContext
{
    public function __construct(
        public bool $pull_request_merged,
        public ?string $reporting_environment,
        public ?CarbonInterface $last_recurrence_at,
        public bool $fix_released,
        public bool $fix_deployed_there,
        public ?CarbonInterface $resolved_at,
        public bool $is_internal,
        public CarbonInterface $now,
    ) {}
}
