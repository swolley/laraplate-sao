<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Releases\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\SAO\Filament\Resources\Releases\ReleaseResource;
use Override;

final class EditRelease extends EditRecord
{
    #[Override]
    protected static string $resource = ReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
