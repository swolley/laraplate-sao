<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Projects\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\SAO\Filament\Resources\Projects\ProjectResource;
use Override;

final class ListProjects extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = ProjectResource::class;
}
