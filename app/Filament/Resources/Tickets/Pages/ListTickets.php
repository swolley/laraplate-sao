<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Tickets\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\SAO\Filament\Resources\Tickets\TicketResource;
use Override;

final class ListTickets extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = TicketResource::class;
}
