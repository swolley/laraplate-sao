<?php

declare(strict_types=1);

namespace Modules\SAO\Data;

use Illuminate\Support\Collection;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;

/**
 * One column of the ticket board: a status and the visible tickets sitting in
 * it. A column with no ticket is still a column, so the board shows the whole
 * process, not only the parts currently in use.
 */
final readonly class BoardColumn
{
    /**
     * @param  Collection<int, Ticket>  $tickets
     */
    public function __construct(
        public TicketStatus $status,
        public Collection $tickets,
    ) {}
}
