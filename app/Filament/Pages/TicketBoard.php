<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Modules\Core\Support\PermissionName;
use Modules\SAO\Data\BoardColumn;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Exceptions\TransitionNotAllowedException;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Services\TicketBoardService;
use Modules\SAO\Services\TicketQueryService;
use Modules\SAO\Services\WorkflowService;
use Override;
use UnitEnum;

/**
 * The per-project board. Columns and cards are a read model over
 * {@see TicketBoardService}; a card moves only through the transitions
 * {@see WorkflowService::availableTransitions()} allows, executed by
 * {@see WorkflowService::transition()} — the board is one more caller of the
 * single transition path, never an ACL or workflow bypass.
 *
 * @property-read array<string, mixed> $data
 */
final class TicketBoard extends Page
{
    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public ?int $projectId = null;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'SAO';

    #[Override]
    protected static ?int $navigationSort = 4;

    #[Override]
    protected static ?string $navigationLabel = 'Board';

    #[Override]
    protected static ?string $title = 'Board';

    #[Override]
    protected string $view = 'sao::filament.pages.ticket-board';

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'sao/board';
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('projectId')
                    ->label('Project')
                    ->options(Project::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->live()
                    ->afterStateUpdated(fn (mixed $state): ?int => $this->projectId = $state === null ? null : (int) $state)
                    ->placeholder('Choose a project'),
            ]);
    }

    /**
     * The board columns for the currently selected project, recomputed each
     * render so a move immediately reflects.
     *
     * @return Collection<int, BoardColumn>
     */
    public function columns(): Collection
    {
        $projectId = $this->resolveProjectId();

        if ($projectId === null) {
            return collect();
        }

        $project = Project::query()->find($projectId);

        if (! $project instanceof Project) {
            return collect();
        }

        return app(TicketBoardService::class)->for($project);
    }

    /**
     * The transitions a given ticket may take right now, for the card menu.
     *
     * @return array<int, string>
     */
    public function transitionsFor(int $ticketId): array
    {
        $ticket = app(TicketQueryService::class)->visible()->find($ticketId);

        if (! $ticket instanceof Ticket) {
            return [];
        }

        return app(WorkflowService::class)
            ->availableTransitions($ticket)
            ->mapWithKeys(static fn ($transition): array => [$transition->to_status_id => $transition->label])
            ->all();
    }

    public function move(int $ticketId, int $toStatusId): void
    {
        $ticket = app(TicketQueryService::class)->visible()->find($ticketId);

        if (! $ticket instanceof Ticket) {
            Notification::make()->title('Ticket not found or not visible.')->danger()->send();

            return;
        }

        $this->authorize(PermissionName::forClass(Ticket::class, 'update'));

        $status = TicketStatus::query()->find($toStatusId);

        if (! $status instanceof TicketStatus) {
            Notification::make()->title('Unknown status.')->danger()->send();

            return;
        }

        try {
            app(WorkflowService::class)->transition($ticket, $status, ChangeContext::forUser($this->currentUser()));
            Notification::make()->title("{$ticket->key} moved to {$status->name}.")->success()->send();
        } catch (TransitionNotAllowedException) {
            Notification::make()->title('That move is not allowed by the workflow.')->danger()->send();
        }
    }

    private function resolveProjectId(): ?int
    {
        $value = $this->projectId ?? ($this->data['projectId'] ?? null);

        return $value === null ? null : (int) $value;
    }

    private function currentUser(): \Modules\Core\Models\User
    {
        /** @var \Modules\Core\Models\User $user */
        return auth()->user();
    }
}
