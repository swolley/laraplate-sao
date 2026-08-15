<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Contracts;

use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\Page;

/**
 * Read commits and ranges, compare branches, read a file at a ref, open PRs.
 *
 * Contract shape only in phase 3a; a real driver implements it in phase 5.
 */
interface VcsCapability
{
    public function commits(BindingContext $context, string $range, ?string $cursor = null): Page;

    /**
     * @return array<string, mixed>
     */
    public function compare(BindingContext $context, string $base, string $head): array;

    public function fileAtRef(BindingContext $context, string $path, string $ref): ?string;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function openPullRequest(BindingContext $context, array $attributes): array;
}
