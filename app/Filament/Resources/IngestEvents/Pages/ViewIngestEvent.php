<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\IngestEvents\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\SAO\Filament\Resources\IngestEvents\IngestEventResource;
use Override;

final class ViewIngestEvent extends ViewRecord
{
    #[Override]
    protected static string $resource = IngestEventResource::class;
}
