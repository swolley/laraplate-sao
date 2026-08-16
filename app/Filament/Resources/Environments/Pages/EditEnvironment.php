<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Environments\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\SAO\Filament\Resources\Environments\EnvironmentResource;
use Override;

final class EditEnvironment extends EditRecord
{
    #[Override]
    protected static string $resource = EnvironmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
