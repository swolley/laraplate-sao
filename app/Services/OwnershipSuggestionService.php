<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Data\OwnershipEvidence;
use Modules\SAO\Models\OwnershipSuggestion;
use Modules\SAO\Models\Ticket;

/**
 * Turns code evidence into a single, persisted ownership proposal. The winner
 * is chosen deterministically — strongest rule first (CODEOWNERS over blame
 * over recent touch over path), then highest score, then lowest user id as a
 * final stable tie-break — so the same evidence always yields the same
 * suggestion. It records the choice; it never sets the assignee (D14).
 */
final class OwnershipSuggestionService
{
    /**
     * @param  list<OwnershipEvidence>  $evidence
     */
    public function suggest(Ticket $ticket, array $evidence): ?OwnershipSuggestion
    {
        $winner = $this->pickWinner($evidence);

        if ($winner === null) {
            return null;
        }

        return OwnershipSuggestion::query()->create([
            'ticket_id' => $ticket->getKey(),
            'suggested_user_id' => $winner->userId,
            'rule' => $winner->rule,
            'score' => $winner->score,
            'evidence' => [
                'paths' => $winner->paths,
                'detail' => $winner->detail,
            ],
        ]);
    }

    /**
     * @param  list<OwnershipEvidence>  $evidence
     */
    private function pickWinner(array $evidence): ?OwnershipEvidence
    {
        $winner = null;

        foreach ($evidence as $candidate) {
            if ($winner === null || $this->beats($candidate, $winner)) {
                $winner = $candidate;
            }
        }

        return $winner;
    }

    private function beats(OwnershipEvidence $candidate, OwnershipEvidence $incumbent): bool
    {
        $candidatePrecedence = $candidate->rule->precedence();
        $incumbentPrecedence = $incumbent->rule->precedence();

        if ($candidatePrecedence !== $incumbentPrecedence) {
            return $candidatePrecedence < $incumbentPrecedence;
        }

        if ($candidate->score !== $incumbent->score) {
            return $candidate->score > $incumbent->score;
        }

        return $candidate->userId < $incumbent->userId;
    }
}
