<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\TicketTypes\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\SAO\Filament\Resources\TicketTypes\TicketTypeResource;
use Override;

final class CreateTicketType extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = TicketTypeResource::class;
}
