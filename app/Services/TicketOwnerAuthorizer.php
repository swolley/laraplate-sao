<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Search\Contracts\IOwnerAuthorizer;
use Modules\SAO\Models\Ticket;
use Override;

/**
 * A media attached to a ticket is visible to whoever may see the ticket (M16), through the
 * same read path the ticket screens use.
 */
final readonly class TicketOwnerAuthorizer implements IOwnerAuthorizer
{
    public function __construct(private TicketQueryService $tickets) {}

    #[Override]
    public function ownerType(): string
    {
        return Ticket::class;
    }

    #[Override]
    public function visibleOwners(): Builder
    {
        return $this->tickets->visible();
    }
}
