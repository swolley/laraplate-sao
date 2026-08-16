<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Drivers\Contracts\VcsCapability;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Enums\ChangeRefType;
use Modules\SAO\Models\ChangeRef;

/**
 * Discovers the files a pull request changed, by comparing its base and head
 * through the `vcs` capability. It reads the two host-shaped payload layouts —
 * GitHub's `files[].filename` and GitLab's `diffs[].new_path`/`old_path` — so
 * the coordinator can run the ownership resolvers without a caller naming the
 * paths by hand. A ref that is not a pull request, or one missing its base/head,
 * yields nothing.
 */
final class PullRequestChangedPathsResolver
{
    /**
     * @return list<string>
     */
    public function resolve(VcsCapability $vcs, BindingContext $context, ChangeRef $ref): array
    {
        if ($ref->type !== ChangeRefType::PullRequest || $ref->base_ref === null || $ref->head_ref === null) {
            return [];
        }

        return $this->extractPaths($vcs->compare($context, $ref->base_ref, $ref->head_ref));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function extractPaths(array $payload): array
    {
        $paths = [];

        foreach ($this->rows($payload, 'files') as $file) {
            if (is_array($file) && isset($file['filename']) && is_string($file['filename'])) {
                $paths[] = $file['filename'];
            }
        }

        foreach ($this->rows($payload, 'diffs') as $diff) {
            if (! is_array($diff)) {
                continue;
            }

            $path = $diff['new_path'] ?? $diff['old_path'] ?? null;

            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<mixed>
     */
    private function rows(array $payload, string $key): array
    {
        $rows = $payload[$key] ?? null;

        return is_array($rows) ? array_values($rows) : [];
    }
}
