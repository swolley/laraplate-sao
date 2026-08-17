<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\SourceProfiles\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\SAO\Filament\Resources\SourceProfiles\SourceProfileResource;
use Override;

final class ListSourceProfiles extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = SourceProfileResource::class;
}
