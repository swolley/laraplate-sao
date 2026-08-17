<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Enums\SignalOpenOutcome;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Signal;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketType;

/**
 * Opens a SAO ticket from an error signal, deterministically and once.
 *
 * A signal is machine-managed grouping; this is the one place it becomes work.
 * The open is idempotent by the signal's persisted `ticket_id` — a signal that
 * already carries a ticket is never opened again — so the caller may retry or
 * rescan freely. The ticket is created through {@see TicketCreationService} on
 * the project's default type with an automation-origin {@see ChangeContext},
 * then linked back onto the signal. Every reason it does not open is returned
 * explicitly rather than silently skipped. Reopening a resolved signal's ticket
 * on recurrence is the closure engine's concern, not this.
 */
final readonly class SignalTicketOpener
{
    private const string SOURCE_KEY = 'signal';

    public function __construct(private TicketCreationService $creation) {}

    /**
     * @return array{outcome: SignalOpenOutcome, ticket: ?Ticket}
     */
    public function open(Signal $signal): array
    {
        if ($signal->ticket_id !== null) {
            return $this->result(SignalOpenOutcome::AlreadyLinked);
        }

        $project = $signal->project;

        if (! $project instanceof Project || ! $project->is_active) {
            return $this->result(SignalOpenOutcome::ProjectUnavailable);
        }

        $type = $project->defaultTicketType();

        if (! $type instanceof TicketType) {
            return $this->result(SignalOpenOutcome::NoDefaultType);
        }

        $ticket = $this->creation->open($project, $type, [
            'title' => $this->title($signal),
            'description' => $this->description($signal),
        ], ChangeContext::forAutomation(self::SOURCE_KEY));

        $signal->update(['ticket_id' => $ticket->getKey()]);

        return $this->result(SignalOpenOutcome::Opened, $ticket);
    }

    /**
     * @return array{outcome: SignalOpenOutcome, ticket: ?Ticket}
     */
    private function result(SignalOpenOutcome $outcome, ?Ticket $ticket = null): array
    {
        return ['outcome' => $outcome, 'ticket' => $ticket];
    }

    private function title(Signal $signal): string
    {
        return 'Signal: ' . $signal->group_key;
    }

    private function description(Signal $signal): string
    {
        return "Automatically opened from error signal {$signal->group_key} ({$signal->occurrence_count} occurrences recorded).";
    }
}
