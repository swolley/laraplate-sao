<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\ClosurePolicies\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\SAO\Filament\Resources\ClosurePolicies\ClosurePolicyResource;
use Override;

final class ListClosurePolicies extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = ClosurePolicyResource::class;
}
