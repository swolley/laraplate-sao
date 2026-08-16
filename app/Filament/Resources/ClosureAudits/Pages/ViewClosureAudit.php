<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\ClosureAudits\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\SAO\Filament\Resources\ClosureAudits\ClosureAuditResource;
use Override;

final class ViewClosureAudit extends ViewRecord
{
    #[Override]
    protected static string $resource = ClosureAuditResource::class;
}
