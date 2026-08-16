<?php

declare(strict_types=1);

namespace Modules\SAO\Data;

/**
 * The propagation state of a ticket's fix: whether the pull request is merged,
 * whether a shipped release carries it, and where that release is (and is not)
 * running. This is the "already fixed on dev, deploy missing" answer, computed
 * from persisted facts alone — no driver call.
 */
final readonly class FixStatus
{
    /**
     * @param  list<string>  $deployed_environments
     * @param  list<string>  $missing_environments
     */
    public function __construct(
        public bool $pull_request_merged,
        public bool $fix_released,
        public ?string $released_version,
        public array $deployed_environments,
        public array $missing_environments,
        public ?bool $deployed_there,
    ) {}
}
