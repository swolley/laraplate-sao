<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\ClosurePolicies\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\SAO\Filament\Resources\ClosurePolicies\ClosurePolicyResource;
use Override;

final class CreateClosurePolicy extends CreateRecord
{
    #[Override]
    protected static string $resource = ClosurePolicyResource::class;
}
