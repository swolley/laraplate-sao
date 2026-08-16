<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Releases\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\SAO\Filament\Resources\Releases\ReleaseResource;
use Override;

final class CreateRelease extends CreateRecord
{
    #[Override]
    protected static string $resource = ReleaseResource::class;
}
