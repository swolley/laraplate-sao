<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The health of a release judged from the signals seen since it was deployed.
 * Deliberately post-hoc evidence, never a promote/rollback gate.
 */
enum ReleaseHealthVerdict: string
{
    /** No new signals and error rate flat or down versus the baseline release. */
    case Healthy = 'healthy';

    /** New signals appeared, but below the regression threshold. */
    case Degraded = 'degraded';

    /** New or worsening signals past the configured regression threshold. */
    case Regressed = 'regressed';

    /** Not enough data to judge (no deploy time, no occurrences and no baseline). */
    case Unknown = 'unknown';
}
