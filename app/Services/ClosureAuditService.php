<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Closure\ClosureDecision;
use Modules\SAO\Models\ClosureAudit;
use Modules\SAO\Models\ClosurePolicy;
use Modules\SAO\Models\SignalOccurrence;
use Modules\SAO\Models\Ticket;

/**
 * Persists the audit trail of evidence-based closures and their reversals. A
 * recorded closure stores exactly which conditions held ("closed because"); a
 * later recurrence reopens it and stamps "returned after", flagging the closure
 * premature. That record is both the reversal and the data that says whether
 * the configured durations are tuned correctly.
 */
final class ClosureAuditService
{
    public function record(
        Ticket $ticket,
        ClosureDecision $decision,
        ?string $reportingEnvironment = null,
        ?ClosurePolicy $policy = null,
    ): ClosureAudit {
        return ClosureAudit::query()->create([
            'ticket_id' => $ticket->getKey(),
            'closure_policy_id' => $policy?->getKey(),
            'action' => $decision->action,
            'conditions_held' => $decision->toEvidence(),
            'reporting_environment' => $reportingEnvironment,
            'closed_at' => now(),
        ]);
    }

    /**
     * Reopen a recorded closure because the signal reappeared: stamp when it
     * returned, how long after the closure, which occurrence proved it, and mark
     * the closure premature.
     */
    public function reopenForRecurrence(ClosureAudit $audit, SignalOccurrence $occurrence): ClosureAudit
    {
        $returnedAt = $occurrence->occurred_at ?? now();

        $audit->forceFill([
            'reopened_at' => $returnedAt,
            'returned_after_seconds' => $audit->closed_at->diffInSeconds($returnedAt, absolute: true),
            'returned_occurrence_id' => $occurrence->getKey(),
            'is_premature' => true,
        ])->save();

        return $audit;
    }
}
