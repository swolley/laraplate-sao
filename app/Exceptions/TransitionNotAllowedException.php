<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use RuntimeException;

final class TransitionNotAllowedException extends RuntimeException
{
    public static function between(Ticket $ticket, TicketStatus $to): self
    {
        return new self(
            "Ticket {$ticket->key} cannot move to \"{$to->name}\": its workflow scheme declares no such transition.",
        );
    }
}
