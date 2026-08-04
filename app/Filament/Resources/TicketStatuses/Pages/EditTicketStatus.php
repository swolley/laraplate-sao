<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\TicketStatuses\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\SAO\Filament\Resources\TicketStatuses\TicketStatusResource;
use Override;

final class EditTicketStatus extends EditRecord
{
    #[Override]
    protected static string $resource = TicketStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
