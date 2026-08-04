<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\WorkflowSchemes\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\SAO\Filament\Resources\WorkflowSchemes\WorkflowSchemeResource;
use Override;

final class ListWorkflowSchemes extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = WorkflowSchemeResource::class;
}
