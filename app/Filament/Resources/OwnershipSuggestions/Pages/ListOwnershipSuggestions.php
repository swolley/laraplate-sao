<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\OwnershipSuggestions\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\SAO\Filament\Resources\OwnershipSuggestions\OwnershipSuggestionResource;
use Override;

final class ListOwnershipSuggestions extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = OwnershipSuggestionResource::class;

    /**
     * Suggestions are produced from code evidence, so the list offers no
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
