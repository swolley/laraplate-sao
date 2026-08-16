<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Environments\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\SAO\Filament\Resources\Environments\EnvironmentResource;
use Override;

final class ListEnvironments extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = EnvironmentResource::class;
}
