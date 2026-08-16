<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Connections\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\SAO\Filament\Resources\Connections\ConnectionResource;
use Override;

final class EditConnection extends EditRecord
{
    #[Override]
    protected static string $resource = ConnectionResource::class;
}
