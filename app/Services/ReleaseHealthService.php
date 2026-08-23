<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Modules\SAO\Data\ReleaseHealth;
use Modules\SAO\Enums\ReleaseHealthVerdict;
use Modules\SAO\Models\Environment;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\Signal;

/**
 * Judges a release's health from the signals seen since it was deployed,
 * compared like-for-like against the previous release. A pure read model over
 * persisted facts — no driver call, and never a promote/rollback gate (the
 * canary control plane stays in the deployment platform).
 *
 * The window is currently anchored on {@see Release::$released_at}; once the
 * deploy-ingest feature lands it can be anchored on a real deployment's
 * `finished_at` for a precise, per-deploy verdict.
 */
final class ReleaseHealthService
{
    public function forRelease(Release $release, ?Environment $environment = null, ?CarbonInterval $window = null): ReleaseHealth
    {
        $anchor = $release->released_at;

        if ($anchor === null) {
            return ReleaseHealth::unknown();
        }

        $window ??= CarbonInterval::days((int) config('sao.release_health.window_days', 7));
        $windowStart = $anchor->copy();
        $fullEnd = $windowStart->copy()->add($window);
        $windowEnd = now()->lessThan($fullEnd) ? now() : $fullEnd;

        if ($windowEnd->lessThanOrEqualTo($windowStart)) {
            return ReleaseHealth::unknown($windowStart, $windowEnd);
        }

        $environmentName = $environment?->name;
        $windowClosure = $this->occurrencesBetween($windowStart, $windowEnd, $environmentName);

        $ticketIds = $release->ticketReleases()->pluck('ticket_id')->all();

        /** @var \Illuminate\Support\Collection<int, Signal> $signals */
        $signals = Signal::query()
            ->where(function (Builder $query) use ($release, $ticketIds): void {
                $query->where('project_id', $release->project_id);
                if ($ticketIds !== []) {
                    $query->orWhereIn('ticket_id', $ticketIds);
                }
            })
            ->whereHas('occurrences', $windowClosure)
            ->withCount(['occurrences as window_count' => $windowClosure])
            ->get();

        $totalOccurrences = (int) $signals->sum('window_count');

        $newSignals = $signals
            ->filter(fn (Signal $signal): bool => $signal->first_seen_at !== null
                && $signal->first_seen_at->betweenIncluded($windowStart, $windowEnd))
            ->pluck('group_key')
            ->values()
            ->all();

        [$baseline, $baselineCounts, $baselineTotal] = $this->baseline($release, $windowStart, $windowEnd, $environmentName);

        $regressedSignals = $this->regressed($signals, $windowStart, $baseline !== null ? $baselineCounts : null);

        $deltaPct = ($baseline !== null && $baselineTotal > 0)
            ? (($totalOccurrences - $baselineTotal) / $baselineTotal) * 100.0
            : null;

        return new ReleaseHealth(
            verdict: $this->verdict($newSignals, $regressedSignals, $totalOccurrences, $baseline !== null),
            windowStart: $windowStart,
            windowEnd: $windowEnd,
            baseline: $baseline,
            newSignals: $newSignals,
            regressedSignals: $regressedSignals,
            totalOccurrences: $totalOccurrences,
            occurrenceDeltaPct: $deltaPct,
            contributing: $signals->sortByDesc('window_count')->take(10)->values()->all(),
        );
    }

    /**
     * @return callable(Builder): void
     */
    private function occurrencesBetween(CarbonInterface $start, CarbonInterface $end, ?string $environmentName): callable
    {
        return function (Builder $query) use ($start, $end, $environmentName): void {
            $query->whereBetween('occurred_at', [$start, $end]);
            if ($environmentName !== null) {
                $query->where('environment', $environmentName);
            }
        };
    }

    /**
     * The previous release and its per-group-key occurrence counts over an
     * equal-length window after its own deploy.
     *
     * @return array{0: ?Release, 1: array<string, int>, 2: int}
     */
    private function baseline(Release $release, CarbonInterface $windowStart, CarbonInterface $windowEnd, ?string $environmentName): array
    {
        $baseline = Release::query()
            ->where('project_id', $release->project_id)
            ->whereNotNull('released_at')
            ->where('released_at', '<', $windowStart)
            ->orderByDesc('released_at')
            ->first();

        if (! $baseline instanceof Release || $baseline->released_at === null) {
            return [null, [], 0];
        }

        $elapsedSeconds = max(0, $windowEnd->getTimestamp() - $windowStart->getTimestamp());
        $baselineStart = $baseline->released_at->copy();
        $baselineEnd = $baselineStart->copy()->addSeconds($elapsedSeconds);
        $baselineClosure = $this->occurrencesBetween($baselineStart, $baselineEnd, $environmentName);

        $counts = [];
        $total = 0;

        Signal::query()
            ->where('project_id', $release->project_id)
            ->withCount(['occurrences as baseline_count' => $baselineClosure])
            ->get()
            ->each(function (Signal $signal) use (&$counts, &$total): void {
                $count = (int) $signal->getAttribute('baseline_count');
                $counts[$signal->group_key] = $count;
                $total += $count;
            });

        return [$baseline, $counts, $total];
    }

    /**
     * Existing group keys (first seen before the window) whose in-window count
     * both clears the noise floor and rises past the baseline by the configured
     * factor. Requires a baseline — without one, nothing can be called a
     * regression.
     *
     * @param  \Illuminate\Support\Collection<int, Signal>  $signals
     * @param  array<string, int>|null  $baselineCounts
     * @return list<string>
     */
    private function regressed($signals, CarbonInterface $windowStart, ?array $baselineCounts): array
    {
        if ($baselineCounts === null) {
            return [];
        }

        $factor = (float) config('sao.release_health.regression_rate_factor', 1.5);
        $minOccurrences = (int) config('sao.release_health.regression_min_occurrences', 3);

        return $signals
            ->filter(function (Signal $signal) use ($windowStart, $baselineCounts, $factor, $minOccurrences): bool {
                $isExisting = $signal->first_seen_at !== null && $signal->first_seen_at->lessThan($windowStart);
                if (! $isExisting) {
                    return false;
                }

                $current = (int) $signal->getAttribute('window_count');
                $before = $baselineCounts[$signal->group_key] ?? 0;

                return $current >= $minOccurrences && $current > $before * $factor;
            })
            ->pluck('group_key')
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $newSignals
     * @param  list<string>  $regressedSignals
     */
    private function verdict(array $newSignals, array $regressedSignals, int $totalOccurrences, bool $hasBaseline): ReleaseHealthVerdict
    {
        if ($totalOccurrences === 0 && $newSignals === [] && ! $hasBaseline) {
            return ReleaseHealthVerdict::Unknown;
        }

        $regressedNewThreshold = (int) config('sao.release_health.regressed_new_signals', 3);

        return match (true) {
            $regressedSignals !== [] || count($newSignals) >= $regressedNewThreshold => ReleaseHealthVerdict::Regressed,
            $newSignals !== [] => ReleaseHealthVerdict::Degraded,
            default => ReleaseHealthVerdict::Healthy,
        };
    }
}
