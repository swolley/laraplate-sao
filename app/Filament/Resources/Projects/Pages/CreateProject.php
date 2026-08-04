<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Projects\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\SAO\Filament\Resources\Projects\ProjectResource;
use Override;

final class CreateProject extends CreateRecord
{
    #[Override]
    protected static string $resource = ProjectResource::class;
}
