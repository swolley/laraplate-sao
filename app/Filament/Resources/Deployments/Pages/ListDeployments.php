<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Deployments\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\SAO\Filament\Resources\Deployments\DeploymentResource;
use Override;

final class ListDeployments extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = DeploymentResource::class;

    /**
     * Deployments are written by the deploy ingest, so the list offers no
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
