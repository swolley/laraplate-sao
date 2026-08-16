<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Data\FixStatus;
use Modules\SAO\Enums\ReleaseStatus;
use Modules\SAO\Models\Environment;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\Ticket;

/**
 * Reads a ticket's fix-propagation state from persisted facts: the merged
 * pull request, the shipped release attributed to it, and which of the
 * project's environments run that release's version. Answers "already fixed
 * on dev, deploy missing" without calling any driver.
 */
final class FixStatusResolver
{
    public function forTicket(Ticket $ticket, ?string $reportingEnvironment = null): FixStatus
    {
        $pullRequestMerged = $ticket->changeRefs()->mergedPullRequests()->exists();

        $shippedRelease = $ticket->releases()
            ->where('status', ReleaseStatus::Shipped->value)
            ->orderByDesc('released_at')
            ->first();

        if (! $shippedRelease instanceof Release) {
            return new FixStatus(
                pull_request_merged: $pullRequestMerged,
                fix_released: false,
                released_version: null,
                deployed_environments: [],
                missing_environments: [],
                deployed_there: $reportingEnvironment === null ? null : false,
            );
        }

        $version = $shippedRelease->version;
        $environments = $ticket->project->environments()->orderBy('name')->get();

        $deployed = $environments
            ->filter(fn (Environment $environment): bool => $environment->current_version === $version)
            ->map(fn (Environment $environment): string => $environment->name)
            ->values()
            ->all();

        $missing = $environments
            ->reject(fn (Environment $environment): bool => $environment->current_version === $version)
            ->map(fn (Environment $environment): string => $environment->name)
            ->values()
            ->all();

        return new FixStatus(
            pull_request_merged: $pullRequestMerged,
            fix_released: true,
            released_version: $version,
            deployed_environments: $deployed,
            missing_environments: $missing,
            deployed_there: $reportingEnvironment === null
                ? null
                : in_array($reportingEnvironment, $deployed, true),
        );
    }
}
