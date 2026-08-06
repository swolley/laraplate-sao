<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Tickets\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Modules\SAO\Filament\Resources\Tickets\TicketResource;
use Override;

final class EditTicket extends EditRecord
{
    #[Override]
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
