<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Enums\ClosureAction;
use Modules\SAO\Models\ClosureAudit;
use Modules\SAO\Models\ClosurePolicy;
use Modules\SAO\Models\Ticket;

/**
 * Runs a ticket's active closure policies, gating whether a satisfied policy may
 * actually close the ticket by the module's auto-close setting.
 *
 * By default (`sao.closure.auto_close.enabled` off) every `close` policy is
 * downgraded to `propose`: the evidence is evaluated and a proposal is recorded,
 * but the ticket is never moved. With the setting on, a satisfied `close` policy
 * closes the ticket through {@see ClosureApplicationService} (audited and
 * auto-reversible). Policies authored as `propose`/`notify_only` are unaffected.
 */
final readonly class ClosureCoordinator
{
    public function __construct(private ClosureApplicationService $application) {}

    /**
     * @return list<ClosureAudit>
     */
    public function forTicket(Ticket $ticket, ?string $reportingEnvironment = null): array
    {
        $autoClose = (bool) config('sao.closure.auto_close.enabled', false);

        /** @var \Illuminate\Support\Collection<int, ClosurePolicy> $policies */
        $policies = ClosurePolicy::query()
            ->where('project_id', $ticket->project_id)
            ->where('is_active', true)
            ->get();

        $audits = [];

        foreach ($policies as $policy) {
            if (! $autoClose && $policy->action === ClosureAction::Close) {
                // In-memory downgrade only — never persisted — so the recorded
                // audit is a proposal and no transition runs.
                $policy->action = ClosureAction::Propose;
            }

            $audit = $this->application->apply($ticket, $policy, $reportingEnvironment);

            if ($audit instanceof ClosureAudit) {
                $audits[] = $audit;
            }
        }

        return $audits;
    }
}
