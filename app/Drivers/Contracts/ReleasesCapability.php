<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Contracts;

use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Drivers\Support\Page;

/**
 * List tags and find the first tag containing a commit.
 *
 * Contract shape gets a full conformance battery in phase 3a; a real driver
 * implements it in phase 5.
 */
interface ReleasesCapability
{
    public function tags(ConnectionContext $context, ?string $cursor = null): Page;

    public function firstTagContaining(ConnectionContext $context, string $commitSha): ?string;
}
