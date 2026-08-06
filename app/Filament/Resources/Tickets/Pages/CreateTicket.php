<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Tickets\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Filament\Resources\Tickets\TicketResource;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Services\TicketCreationService;
use Override;

final class CreateTicket extends CreateRecord
{
    #[Override]
    protected static string $resource = TicketResource::class;

    /**
     * Creation is delegated rather than performed here.
     *
     * The key allocation and the opening status come from the domain services,
     * because the API and phase 2's automation open tickets too — and the module
     * forbids orchestration logic in a UI layer, which is what keeps the
     * headless mode real rather than aspirational.
     *
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();

        return app(TicketCreationService::class)->open(
            Project::query()->findOrFail($data['project_id']),
            TicketType::query()->findOrFail($data['ticket_type_id']),
            $data,
            $user === null
                ? ChangeContext::forAutomation('filament')
                : ChangeContext::forUser($user),
        );
    }
}
