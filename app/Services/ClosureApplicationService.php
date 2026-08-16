<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Carbon\CarbonInterface;
use Modules\SAO\Closure\ClosureEvaluator;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Enums\ClosureAction;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Exceptions\ClosureTransitionUnavailableException;
use Modules\SAO\Models\ClosureAudit;
use Modules\SAO\Models\ClosurePolicy;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\WorkflowTransition;

/**
 * Turns a satisfied closure policy into action. It ties together the three
 * deterministic pieces already built — resolve the context, evaluate the
 * policy, record the audit — and, for a `close` policy, moves the ticket
 * through {@see WorkflowService} so the one path to a status change stays the
 * one path: automation does not write `ticket_status_id` directly.
 *
 * A policy that does not hold does nothing. A `notify_only` policy records
 * nothing here (notification is delivered elsewhere). Only `close` and
 * `propose` leave a `ClosureAudit`.
 */
final class ClosureApplicationService
{
    public function __construct(
        private readonly ClosureContextResolver $contextResolver,
        private readonly ClosureEvaluator $evaluator,
        private readonly ClosureAuditService $audits,
        private readonly WorkflowService $workflow,
    ) {}

    public function apply(
        Ticket $ticket,
        ClosurePolicy $policy,
        ?string $reportingEnvironment = null,
        ?CarbonInterface $now = null,
    ): ?ClosureAudit {
        $context = $this->contextResolver->forTicket($ticket, $reportingEnvironment, $now);
        $decision = $this->evaluator->evaluate($policy, $context);

        if (! $decision->satisfied || $decision->action === ClosureAction::NotifyOnly) {
            return null;
        }

        if ($decision->action === ClosureAction::Close) {
            $this->closeTicket($ticket);
        }

        return $this->audits->record($ticket, $decision, $reportingEnvironment, $policy);
    }

    /**
     * Move the ticket to a closed status through the only legal path. A closure
     * is a validated fix, so the target is a `closed` category, never `rejected`;
     * and only a permission-free transition is used, since automation holds no
     * user to satisfy a required permission.
     */
    private function closeTicket(Ticket $ticket): void
    {
        $target = $this->workflow->availableTransitions($ticket)
            ->filter(static fn (WorkflowTransition $transition): bool => $transition->required_permission === null)
            ->map(static fn (WorkflowTransition $transition): ?TicketStatus => TicketStatus::query()->find($transition->to_status_id))
            ->first(static fn (?TicketStatus $status): bool => $status?->category === StatusCategory::Closed);

        if (! $target instanceof TicketStatus) {
            throw ClosureTransitionUnavailableException::forTicket($ticket);
        }

        $this->workflow->transition($ticket, $target, ChangeContext::forAutomation('closure'));
    }
}
