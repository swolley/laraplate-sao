<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\Core\Models\User;
use Modules\SAO\Exceptions\OwnershipSuggestionNotApplicableException;
use Modules\SAO\Models\OwnershipSuggestion;
use Modules\SAO\Models\Ticket;

/**
 * Applies an ownership suggestion by assigning its suggested owner to the
 * ticket. This is the sanctioned **manual** accept — suggestions are never
 * applied automatically (D14), so a human acts through this one path. A
 * suggestion without a resolvable user or ticket is not applicable and is
 * rejected rather than silently ignored.
 */
final readonly class OwnershipSuggestionApplier
{
    public function apply(OwnershipSuggestion $suggestion): Ticket
    {
        $user = $suggestion->suggestedUser;
        $ticket = $suggestion->ticket;

        if (! $user instanceof User) {
            throw OwnershipSuggestionNotApplicableException::noSuggestedUser($suggestion);
        }

        if (! $ticket instanceof Ticket) {
            throw OwnershipSuggestionNotApplicableException::noTicket($suggestion);
        }

        $ticket->update(['assignee_id' => $user->getKey()]);

        return $ticket;
    }
}
