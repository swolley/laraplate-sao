<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\TicketTypes\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\SAO\Filament\Resources\TicketTypes\TicketTypeResource;
use Override;

final class ListTicketTypes extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = TicketTypeResource::class;
}
