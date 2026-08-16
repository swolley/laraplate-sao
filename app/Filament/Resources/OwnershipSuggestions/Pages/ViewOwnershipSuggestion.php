<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\OwnershipSuggestions\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\SAO\Filament\Resources\OwnershipSuggestions\OwnershipSuggestionResource;
use Override;

final class ViewOwnershipSuggestion extends ViewRecord
{
    #[Override]
    protected static string $resource = OwnershipSuggestionResource::class;
}
