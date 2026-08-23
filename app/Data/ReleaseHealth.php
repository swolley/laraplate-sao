<?php

declare(strict_types=1);

namespace Modules\SAO\Data;

use Carbon\CarbonInterface;
use Modules\SAO\Enums\ReleaseHealthVerdict;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\Signal;

/**
 * The read-model verdict for one release: whether the errors seen since it was
 * deployed got worse, judged against the previous release over an equal window.
 * Computed from persisted signals alone — no driver call, no rollout control.
 */
final readonly class ReleaseHealth
{
    /**
     * @param  list<string>  $newSignals        group keys first seen inside the window
     * @param  list<string>  $regressedSignals  existing group keys whose rate rose vs baseline
     * @param  list<Signal>  $contributing      the signals behind the verdict, ranked by window occurrences
     */
    public function __construct(
        public ReleaseHealthVerdict $verdict,
        public ?CarbonInterface $windowStart,
        public ?CarbonInterface $windowEnd,
        public ?Release $baseline,
        public array $newSignals,
        public array $regressedSignals,
        public int $totalOccurrences,
        public ?float $occurrenceDeltaPct,
        public array $contributing,
    ) {}

    /**
     * The verdict when there is nothing to judge (no deploy time / no evidence).
     */
    public static function unknown(?CarbonInterface $windowStart = null, ?CarbonInterface $windowEnd = null): self
    {
        return new self(
            verdict: ReleaseHealthVerdict::Unknown,
            windowStart: $windowStart,
            windowEnd: $windowEnd,
            baseline: null,
            newSignals: [],
            regressedSignals: [],
            totalOccurrences: 0,
            occurrenceDeltaPct: null,
            contributing: [],
        );
    }
}
