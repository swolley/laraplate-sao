<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Connections\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\SAO\Filament\Resources\Connections\ConnectionResource;
use Override;

final class CreateConnection extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = ConnectionResource::class;
}
