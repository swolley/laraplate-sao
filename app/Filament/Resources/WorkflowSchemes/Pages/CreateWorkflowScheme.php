<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\WorkflowSchemes\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\SAO\Filament\Resources\WorkflowSchemes\WorkflowSchemeResource;
use Override;

final class CreateWorkflowScheme extends CreateRecord
{
    #[Override]
    protected static string $resource = WorkflowSchemeResource::class;
}
