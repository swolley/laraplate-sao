<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use Modules\SAO\Models\Ticket;
use RuntimeException;

final class ClosureTransitionUnavailableException extends RuntimeException
{
    public static function forTicket(Ticket $ticket): self
    {
        return new self(
            "No permission-free transition to a closed status is available for ticket [{$ticket->getKey()}], so the closure policy cannot act.",
        );
    }
}
