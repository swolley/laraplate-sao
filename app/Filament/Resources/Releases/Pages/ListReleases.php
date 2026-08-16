<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Releases\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\SAO\Filament\Resources\Releases\ReleaseResource;
use Override;

final class ListReleases extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = ReleaseResource::class;
}
