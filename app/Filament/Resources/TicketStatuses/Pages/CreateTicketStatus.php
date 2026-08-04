<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\TicketStatuses\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\SAO\Filament\Resources\TicketStatuses\TicketStatusResource;
use Override;

final class CreateTicketStatus extends CreateRecord
{
    #[Override]
    protected static string $resource = TicketStatusResource::class;
}
