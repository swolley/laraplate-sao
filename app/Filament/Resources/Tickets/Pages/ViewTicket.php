<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Tickets\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Data\TimelineEntry;
use Modules\SAO\Exceptions\TransitionNotAllowedException;
use Modules\SAO\Filament\Resources\Tickets\TicketResource;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketComment;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\WorkflowTransition;
use Modules\SAO\Services\TicketTimelineService;
use Modules\SAO\Services\WorkflowService;
use Override;
use RuntimeException;

final class ViewTicket extends ViewRecord
{
    #[Override]
    protected static string $resource = TicketResource::class;

    public function postComment(string $body): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        TicketComment::postFor($this->ticket(), $body, ChangeContext::forUser($user));
    }

    /**
     * The merged history of the ticket, for the page to render.
     *
     * @return Collection<int, TimelineEntry>
     */
    public function timeline(): Collection
    {
        return app(TicketTimelineService::class)->for($this->ticket());
    }

    /**
     * The page asks which moves are legal; it never works them out itself. The
     * same service answers for the API and for phase 2's automation, so a rule
     * enforced only here would not be a rule at all.
     *
     * @return array<int, Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        $workflow = app(WorkflowService::class);

        $transitions = $workflow->availableTransitions($this->ticket())
            ->map(fn (WorkflowTransition $transition): Action => Action::make("transition_{$transition->id}")
                ->label($transition->label)
                ->requiresConfirmation()
                ->action(function () use ($transition, $workflow): void {
                    $user = auth()->user();

                    if ($user === null) {
                        return;
                    }

                    try {
                        $workflow->transition(
                            $this->ticket(),
                            TicketStatus::query()->findOrFail($transition->to_status_id),
                            ChangeContext::forUser($user),
                        );
                    } catch (TransitionNotAllowedException $exception) {
                        // The service is the authority, so a refusal is reported
                        // rather than pre-empted by hiding the action.
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }))
            ->all();

        return [...$transitions, EditAction::make()];
    }

    private function ticket(): Ticket
    {
        $record = $this->getRecord();

        if (! $record instanceof Ticket) {
            throw new RuntimeException('The ticket view page was opened without a ticket.');
        }

        return $record;
    }
}
