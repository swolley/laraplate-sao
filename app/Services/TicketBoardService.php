<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Illuminate\Support\Collection;
use Modules\SAO\Data\BoardColumn;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\TicketStatus;

/**
 * The board read model: for one project, the ordered status columns each
 * carrying its visible tickets. It is a projection over what 1a/1b already
 * store — no board, column or card is persisted.
 */
final readonly class TicketBoardService
{
    public function __construct(private TicketQueryService $tickets) {}

    /**
     * @return Collection<int, BoardColumn>
     */
    public function for(Project $project): Collection
    {
        $ticketsByStatus = $this->tickets->visible()
            ->where('project_id', $project->getKey())
            ->with(['type', 'assignee', 'labels'])
            ->get()
            ->groupBy('ticket_status_id');

        return TicketStatus::query()
            ->orderBy('order_column')
            ->get()
            ->map(static fn (TicketStatus $status): BoardColumn => new BoardColumn(
                $status,
                $ticketsByStatus->get($status->getKey(), collect())->values(),
            ))
            ->values();
    }
}
