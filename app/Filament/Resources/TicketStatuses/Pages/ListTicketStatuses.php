<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\TicketStatuses\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\SAO\Filament\Resources\TicketStatuses\TicketStatusResource;
use Override;

final class ListTicketStatuses extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = TicketStatusResource::class;
}
