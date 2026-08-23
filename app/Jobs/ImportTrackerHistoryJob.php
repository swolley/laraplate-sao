<?php

declare(strict_types=1);

namespace Modules\SAO\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\SAO\Enums\ImportScope;
use Modules\SAO\Enums\SyncDirection;
use Modules\SAO\Models\ProjectBinding;
use Modules\SAO\Services\BindingCutoverService;
use Modules\SAO\Services\TrackerImportService;

/**
 * Runs a tracker history import for one binding in the background, then optionally
 * cuts the binding over. Idempotent — a failed-and-retried run skips what already
 * imported — so no stored cursor is needed to be safely resumable. Completion is
 * logged; a future in-app notification hooks in here.
 */
final class ImportTrackerHistoryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        private readonly ProjectBinding $binding,
        private readonly ImportScope $scope,
        private readonly ?SyncDirection $cutover = null,
    ) {}

    public function handle(TrackerImportService $importer, BindingCutoverService $cutoverService): void
    {
        $report = $importer->import($this->binding, $this->scope);

        if ($this->cutover !== null && $report->processed) {
            $cutoverService->cutover($this->binding, $this->cutover);
        }

        Log::info('sao.tracker.import.completed', [
            'binding_id' => $this->binding->getKey(),
            'scope' => $this->scope->value,
            'created' => $report->created,
            'updated' => $report->updated,
            'filtered' => $report->filtered,
            'skipped' => $report->skipped,
            'truncated' => $report->truncated,
            'cutover' => $this->cutover?->value,
        ]);
    }
}
