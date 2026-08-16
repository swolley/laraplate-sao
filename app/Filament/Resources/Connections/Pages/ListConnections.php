<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Connections\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\SAO\Filament\Resources\Connections\ConnectionResource;
use Override;

final class ListConnections extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = ConnectionResource::class;
}
