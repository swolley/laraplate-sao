<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\SAO\Models\Ticket;

/**
 * Turns a {@see TicketSearchCriteria} into a query. It builds strictly on
 * {@see TicketQueryService::visible()}, so a search can never surface a ticket
 * the current user is not allowed to see.
 */
final readonly class TicketSearchService
{
    public function __construct(private TicketQueryService $tickets) {}

    /**
     * @return Builder<Ticket>
     */
    public function search(TicketSearchCriteria $criteria): Builder
    {
        $query = $this->tickets->visible();

        if ($criteria->text !== null) {
            $query->where(static function (Builder $inner) use ($criteria): void {
                $inner->where('title', 'like', "%{$criteria->text}%")
                    ->orWhere('description', 'like', "%{$criteria->text}%");
            });
        }

        if ($criteria->statusId !== null) {
            $query->where('ticket_status_id', $criteria->statusId);
        }

        if ($criteria->typeId !== null) {
            $query->where('ticket_type_id', $criteria->typeId);
        }

        if ($criteria->priority !== null) {
            $query->where('priority', $criteria->priority->value);
        }

        if ($criteria->assigneeId !== null) {
            $query->where('assignee_id', $criteria->assigneeId);
        }

        if ($criteria->labelId !== null) {
            $query->whereHas('labels', static fn (Builder $labels): Builder => $labels->whereKey($criteria->labelId));
        }

        if ($criteria->dueBefore !== null) {
            $query->whereNotNull('due_at')->where('due_at', '<=', $criteria->dueBefore);
        }

        if ($criteria->dueAfter !== null) {
            $query->whereNotNull('due_at')->where('due_at', '>=', $criteria->dueAfter);
        }

        if ($criteria->isOverdue) {
            $query->overdue();
        }

        return $query;
    }
}
