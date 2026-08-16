<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Drivers\Contracts\BlameCapability;
use Modules\SAO\Drivers\Contracts\VcsCapability;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Models\ChangeRef;
use Modules\SAO\Models\OwnershipSuggestion;
use Modules\SAO\Models\Ticket;

/**
 * Drives an ownership suggestion end to end: it builds the identity map for the
 * provider, runs every applicable evidence resolver over the touched files and
 * ref, and hands the merged evidence to {@see OwnershipSuggestionService}, which
 * picks the winner and persists the proposal. Blame evidence is gathered only
 * when the connection's driver implements {@see BlameCapability}.
 *
 * The caller supplies the touched paths and ref: deriving them from a merged
 * pull request needs a base/head compare the `vcs` contract does not persist, so
 * that discovery stays the caller's job while the correlation stays here.
 */
final class OwnershipSuggestionCoordinator
{
    public function __construct(
        private readonly ContributorIdentityMap $identityMap,
        private readonly CodeownersOwnershipResolver $codeowners,
        private readonly RecentTouchOwnershipResolver $recentTouch,
        private readonly BlameConcentrationOwnershipResolver $blame,
        private readonly OwnershipSuggestionService $suggestions,
        private readonly PullRequestChangedPathsResolver $changedPaths,
    ) {}

    /**
     * Suggest an owner straight from a merged pull-request reference: the files
     * it changed and the ref to read them at are discovered from the reference
     * itself, so no caller need name them. The head is the ref evidence is read
     * at — that is the code the fix lives in.
     */
    public function suggestForPullRequest(
        Ticket $ticket,
        VcsCapability $vcs,
        BindingContext $context,
        string $provider,
        ChangeRef $pullRequest,
        int $maxCommits = 100,
    ): ?OwnershipSuggestion {
        $paths = $this->changedPaths->resolve($vcs, $context, $pullRequest);

        if ($paths === [] || $pullRequest->head_ref === null) {
            return null;
        }

        return $this->suggestFor($ticket, $vcs, $context, $provider, $paths, $pullRequest->head_ref, $maxCommits);
    }

    /**
     * @param  list<string>  $touchedPaths
     */
    public function suggestFor(
        Ticket $ticket,
        VcsCapability $vcs,
        BindingContext $context,
        string $provider,
        array $touchedPaths,
        string $ref,
        int $maxCommits = 100,
    ): ?OwnershipSuggestion {
        $identityMap = $this->identityMap->forProvider($provider);

        $evidence = [
            ...$this->codeowners->resolve($vcs, $context, $ref, $touchedPaths, $identityMap),
            ...$this->recentTouch->resolve($vcs, $context, $ref, $identityMap, $maxCommits),
        ];

        if ($vcs instanceof BlameCapability) {
            $evidence = [
                ...$evidence,
                ...$this->blame->resolve($vcs, $context, $touchedPaths, $ref, $identityMap),
            ];
        }

        return $this->suggestions->suggest($ticket, $evidence);
    }
}
