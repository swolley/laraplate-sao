<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Environments\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\SAO\Filament\Resources\Environments\EnvironmentResource;
use Override;

final class CreateEnvironment extends CreateRecord
{
    #[Override]
    protected static string $resource = EnvironmentResource::class;
}
