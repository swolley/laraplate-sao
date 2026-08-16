<?php

declare(strict_types=1);

namespace Modules\SAO\Data;

/**
 * How long, from a signal's first sighting, until each truth about its fix
 * became knowable: the fix was merged, a deploy gap ("released but not
 * everywhere") was knowable, and — if it happened — a premature closure was
 * reopened. Each is a nullable interval in seconds; null means the truth is not
 * yet established.
 */
final readonly class TimeToTruth
{
    public function __construct(
        public ?int $time_to_fix_merged_seconds,
        public ?int $time_to_deploy_gap_known_seconds,
        public ?int $time_to_premature_reopen_seconds,
    ) {}
}
