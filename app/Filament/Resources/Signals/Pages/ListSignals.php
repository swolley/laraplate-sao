<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Signals\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\SAO\Filament\Resources\Signals\SignalResource;
use Override;

final class ListSignals extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = SignalResource::class;

    /**
     * Signals are machine-opened, so the list offers no "create" action even to
     * users who could create other records.
     *
     * @return array<int, mixed>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
