<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\SourceProfiles\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\SAO\Filament\Resources\SourceProfiles\SourceProfileResource;
use Override;

final class EditSourceProfile extends EditRecord
{
    #[Override]
    protected static string $resource = SourceProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
