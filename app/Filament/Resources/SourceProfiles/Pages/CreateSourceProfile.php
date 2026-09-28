<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\SourceProfiles\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\SAO\Filament\Resources\SourceProfiles\SourceProfileResource;
use Override;

final class CreateSourceProfile extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = SourceProfileResource::class;
}
