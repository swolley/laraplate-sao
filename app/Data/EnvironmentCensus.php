<?php

declare(strict_types=1);

namespace Modules\SAO\Data;

use Carbon\CarbonInterface;

/**
 * One row of the deploy census: what an environment is running and how fresh
 * that knowledge is. `is_stale` is computed against a caller-chosen TTL so the
 * census never claims certainty it does not have.
 */
final readonly class EnvironmentCensus
{
    public function __construct(
        public string $environment,
        public ?string $version,
        public ?CarbonInterface $last_seen_at,
        public bool $is_stale,
    ) {}
}
