<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\IngestEvents\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\SAO\Filament\Resources\IngestEvents\IngestEventResource;
use Override;

final class ListIngestEvents extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = IngestEventResource::class;

    /**
     * Ingest events are written by the ingest pipeline, so the list offers no
     * "create" action even to users who could create other records.
     *
     * @return array<int, mixed>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
