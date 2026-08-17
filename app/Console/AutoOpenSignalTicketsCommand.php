<?php

declare(strict_types=1);

namespace Modules\SAO\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\SAO\Enums\SignalOpenOutcome;
use Modules\SAO\Enums\SignalState;
use Modules\SAO\Models\Signal;
use Modules\SAO\Services\SignalTicketOpener;

/**
 * Opens a ticket for each open, unlinked signal that has reached the configured
 * occurrence threshold. Idempotent — a signal already carrying a ticket is
 * skipped — and safe to run repeatedly; the scheduled registration is gated by
 * `sao.signals.auto_open.enabled`, but the command itself always runs.
 */
final class AutoOpenSignalTicketsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sao:signals:auto-open {project? : The project name to scope to; omit to scan them all}';

    /**
     * @var string
     */
    protected $description = 'Open tickets from error signals that reached the occurrence threshold';

    public function handle(SignalTicketOpener $opener): int
    {
        $threshold = max(1, (int) config('sao.signals.auto_open.min_occurrences', 1));

        $query = Signal::query()
            ->with('project')
            ->where('state', SignalState::Open)
            ->whereNull('ticket_id')
            ->where('occurrence_count', '>=', $threshold);

        $name = $this->argument('project');

        if (is_string($name) && $name !== '') {
            $query->whereHas('project', static fn (Builder $project): Builder => $project->where('name', $name));
        }

        $signals = $query->get();

        if ($signals->isEmpty()) {
            $this->info('No signals eligible for auto-open.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($signals as $signal) {
            $result = $opener->open($signal);
            $ticket = $result['ticket'];

            $rows[] = [
                (string) ($signal->project->name ?? $signal->project_id),
                $signal->group_key,
                (string) $signal->occurrence_count,
                $result['outcome']->value,
                $ticket?->key ?? '—',
            ];
        }

        $this->table(['Project', 'Signal', 'Occurrences', 'Outcome', 'Ticket'], $rows);

        $opened = collect($rows)->filter(static fn (array $row): bool => $row[3] === SignalOpenOutcome::Opened->value)->count();
        $this->info("Opened {$opened} ticket(s).");

        return self::SUCCESS;
    }
}
