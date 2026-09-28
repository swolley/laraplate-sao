<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Signals\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\SAO\Filament\Resources\Signals\SignalResource;
use Override;

final class EditSignal extends EditRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = SignalResource::class;
}
