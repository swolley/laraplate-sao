<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Data\OwnershipEvidence;
use Modules\SAO\Drivers\Contracts\VcsCapability;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Enums\OwnershipRule;

/**
 * Gathers ownership evidence from a repository's CODEOWNERS file — the
 * strongest, most explicit signal (D14, §9.3). It reads the file at a ref
 * through the `vcs` capability, matches its patterns against the files a fix
 * touched (last matching pattern wins, as git does), and resolves the owner
 * handles to user ids through an injected identity map. Handles absent from the
 * map are skipped: SAO never proposes a user it cannot name.
 *
 * Blame concentration and recent-touch evidence need commit-author data the
 * `vcs` normalization does not yet carry; they remain follow-ups.
 */
final class CodeownersOwnershipResolver
{
    /**
     * The standard locations a CODEOWNERS file may live at, tried in order.
     *
     * @var list<string>
     */
    private const array LOCATIONS = ['CODEOWNERS', '.github/CODEOWNERS', 'docs/CODEOWNERS'];

    /**
     * @param  list<string>  $touchedPaths  The files the fix changed.
     * @param  array<string, int>  $identityMap  CODEOWNERS handle (e.g. `@octocat`) → user id.
     * @return list<OwnershipEvidence>
     */
    public function resolve(VcsCapability $vcs, BindingContext $context, string $ref, array $touchedPaths, array $identityMap): array
    {
        $rules = $this->parse($this->readCodeowners($vcs, $context, $ref));

        if ($rules === []) {
            return [];
        }

        /** @var array<string, list<string>> $pathsByHandle */
        $pathsByHandle = [];

        foreach ($touchedPaths as $path) {
            foreach ($this->ownersFor($rules, $path) as $handle) {
                $pathsByHandle[$handle][] = $path;
            }
        }

        $evidence = [];

        foreach ($pathsByHandle as $handle => $paths) {
            $userId = $identityMap[$handle] ?? null;

            if ($userId === null) {
                continue;
            }

            $evidence[] = new OwnershipEvidence(
                userId: $userId,
                rule: OwnershipRule::Codeowners,
                score: (float) count($paths),
                paths: array_values(array_unique($paths)),
                detail: ['handle' => $handle],
            );
        }

        return $evidence;
    }

    private function readCodeowners(VcsCapability $vcs, BindingContext $context, string $ref): string
    {
        foreach (self::LOCATIONS as $location) {
            $content = $vcs->fileAtRef($context, $location, $ref);

            if ($content !== null && $content !== '') {
                return $content;
            }
        }

        return '';
    }

    /**
     * Parse CODEOWNERS into ordered `[pattern, owners]` rules, keeping file order
     * so the last matching pattern can win.
     *
     * @return list<array{pattern: string, owners: list<string>}>
     */
    private function parse(string $content): array
    {
        $rules = [];

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $tokens = preg_split('/\s+/', $line) ?: [];
            $pattern = array_shift($tokens);

            if ($pattern === null || $tokens === []) {
                continue;
            }

            $rules[] = ['pattern' => $pattern, 'owners' => array_values($tokens)];
        }

        return $rules;
    }

    /**
     * The owners of the last pattern that matches the path — git's semantics.
     *
     * @param  list<array{pattern: string, owners: list<string>}>  $rules
     * @return list<string>
     */
    private function ownersFor(array $rules, string $path): array
    {
        $owners = [];

        foreach ($rules as $rule) {
            if ($this->matches($rule['pattern'], $path)) {
                $owners = $rule['owners'];
            }
        }

        return $owners;
    }

    private function matches(string $pattern, string $path): bool
    {
        $pattern = ltrim($pattern, '/');
        $path = ltrim($path, '/');

        if ($pattern === '*') {
            return true;
        }

        if (str_ends_with($pattern, '/')) {
            return str_starts_with($path, $pattern);
        }

        if (! str_contains($pattern, '/')) {
            return fnmatch($pattern, basename($path));
        }

        return fnmatch($pattern, $path) || str_starts_with($path, $pattern . '/');
    }
}
