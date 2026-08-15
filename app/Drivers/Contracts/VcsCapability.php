<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Contracts;

use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Drivers\Support\Page;

/**
 * Read commits and ranges, compare branches, read a file at a ref, open PRs.
 *
 * Contract shape only in phase 3a; a real driver implements it in phase 5.
 */
interface VcsCapability
{
    public function commits(ConnectionContext $context, string $range, ?string $cursor = null): Page;

    /**
     * @return array<string, mixed>
     */
    public function compare(ConnectionContext $context, string $base, string $head): array;

    public function fileAtRef(ConnectionContext $context, string $path, string $ref): ?string;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function openPullRequest(ConnectionContext $context, array $attributes): array;
}
