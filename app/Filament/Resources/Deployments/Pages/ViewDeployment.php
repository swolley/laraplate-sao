<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Deployments\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\SAO\Filament\Resources\Deployments\DeploymentResource;
use Override;

final class ViewDeployment extends ViewRecord
{
    #[Override]
    protected static string $resource = DeploymentResource::class;
}
