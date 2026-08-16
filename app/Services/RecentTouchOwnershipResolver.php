<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Data\OwnershipEvidence;
use Modules\SAO\Drivers\Contracts\VcsCapability;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Enums\OwnershipRule;

/**
 * Gathers ownership evidence from who recently touched the code: it pages
 * through the commits of a range and counts them per author, resolving the
 * author identity to a user id through an injected identity map. The identity
 * of a commit is its account handle when the host exposes one, else the author
 * email (GitLab carries no handle), so a map keyed by either resolves it.
 *
 * The count is a weaker signal than an explicit CODEOWNERS entry — the
 * `OwnershipRule` precedence reflects that — but it surfaces the de-facto owner
 * of an area a policy file may never have named.
 */
final class RecentTouchOwnershipResolver
{
    /**
     * @param  array<string, int>  $identityMap  Commit handle or email → user id.
     * @return list<OwnershipEvidence>
     */
    public function resolve(
        VcsCapability $vcs,
        BindingContext $context,
        string $range,
        array $identityMap,
        int $maxCommits = 100,
    ): array {
        /** @var array<string, int> $countByIdentity */
        $countByIdentity = [];
        $seen = 0;
        $cursor = null;

        do {
            $page = $vcs->commits($context, $range, $cursor);

            foreach ($page->items as $commit) {
                if ($seen >= $maxCommits) {
                    break 2;
                }

                $seen++;
                $identity = $this->identityOf($commit);

                if ($identity !== null) {
                    $countByIdentity[$identity] = ($countByIdentity[$identity] ?? 0) + 1;
                }
            }

            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        $evidence = [];

        foreach ($countByIdentity as $identity => $count) {
            $userId = $identityMap[$identity] ?? null;

            if ($userId === null) {
                continue;
            }

            $evidence[] = new OwnershipEvidence(
                userId: $userId,
                rule: OwnershipRule::RecentTouch,
                score: (float) $count,
                paths: [],
                detail: ['identity' => $identity, 'commits' => $count],
            );
        }

        return $evidence;
    }

    /**
     * @param  array<string, mixed>  $commit
     */
    private function identityOf(array $commit): ?string
    {
        $handle = $commit['author'] ?? null;

        if (is_string($handle) && $handle !== '') {
            return $handle;
        }

        $email = $commit['author_email'] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }
}
