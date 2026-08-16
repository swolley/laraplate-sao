<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\ClosureAudits\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\SAO\Filament\Resources\ClosureAudits\ClosureAuditResource;
use Override;

final class ListClosureAudits extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = ClosureAuditResource::class;

    /**
     * Closure audits are written by the closure engine, so the list offers no
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
