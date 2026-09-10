<?php

declare(strict_types=1);

namespace Modules\SAO\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\SAO\Enums\ClosureAction;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Models\ClosurePolicy;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\ClosureCoordinator;

/**
 * Evaluates active closure policies over each project's non-terminal tickets and
 * either proposes or (when `sao.closure.auto_close.enabled` is on) applies a
 * satisfied close, through {@see ClosureCoordinator}. The activation surface for
 * evidence-based closure; safe to run repeatedly (closure is audited and, when
 * off, never changes a ticket's state).
 */
final class RunClosureCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sao:closure:run
        {project? : The project name to scope to; omit to scan them all}
        {--env= : The reporting environment to judge recurrence/deploy conditions against}';

    /**
     * @var string
     */
    protected $description = 'Evaluate closure policies over non-terminal tickets (propose, or auto-close when enabled) <fg=bright-red>(🎫 Modules\SAO)</fg=bright-red>';

    public function handle(ClosureCoordinator $coordinator): int
    {
        $reportingEnvironment = $this->option('env');
        $env = is_string($reportingEnvironment) && $reportingEnvironment !== '' ? $reportingEnvironment : null;

        $projectIds = ClosurePolicy::query()->where('is_active', true)->pluck('project_id')->unique()->all();

        $query = Ticket::query()
            ->with('project')
            ->whereIn('project_id', $projectIds)
            ->whereHas('status', static fn (Builder $status): Builder => $status->whereNotIn('category', [
                StatusCategory::Closed->value,
                StatusCategory::Rejected->value,
            ]));

        $name = $this->argument('project');

        if (is_string($name) && $name !== '') {
            $query->whereHas('project', static fn (Builder $project): Builder => $project->where('name', $name));
        }

        $tickets = $query->get();

        $proposed = 0;
        $closed = 0;

        foreach ($tickets as $ticket) {
            foreach ($coordinator->forTicket($ticket, $env) as $audit) {
                $audit->action === ClosureAction::Close ? $closed++ : $proposed++;
            }
        }

        $this->info("Closure evaluated {$tickets->count()} ticket(s): {$closed} closed, {$proposed} proposed.");

        return self::SUCCESS;
    }
}
