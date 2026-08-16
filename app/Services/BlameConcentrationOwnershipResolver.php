<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Data\OwnershipEvidence;
use Modules\SAO\Drivers\Contracts\BlameCapability;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Enums\OwnershipRule;

/**
 * Gathers ownership evidence from where the surviving lines came from: it blames
 * each touched file at a ref and sums the lines each author still owns across
 * them, resolving the author identity to a user id through an injected identity
 * map. Line concentration is a stronger signal than commit frequency but weaker
 * than an explicit CODEOWNERS entry, which the `OwnershipRule` precedence
 * reflects.
 *
 * Requires a driver that implements {@see BlameCapability}; a connection whose
 * driver does not is simply not passed here.
 */
final class BlameConcentrationOwnershipResolver
{
    /**
     * @param  list<string>  $paths  The files the fix touched.
     * @param  array<string, int>  $identityMap  Blame handle or email → user id.
     * @return list<OwnershipEvidence>
     */
    public function resolve(BlameCapability $blame, BindingContext $context, array $paths, string $ref, array $identityMap): array
    {
        /** @var array<string, array{lines: int, paths: list<string>}> $byIdentity */
        $byIdentity = [];

        foreach ($paths as $path) {
            foreach ($blame->blame($context, $path, $ref) as $entry) {
                $identity = $this->identityOf($entry);
                $lines = (int) ($entry['lines'] ?? 0);

                if ($identity === null || $lines <= 0) {
                    continue;
                }

                if (! isset($byIdentity[$identity])) {
                    $byIdentity[$identity] = ['lines' => 0, 'paths' => []];
                }

                $byIdentity[$identity]['lines'] += $lines;
                $byIdentity[$identity]['paths'][] = $path;
            }
        }

        $evidence = [];

        foreach ($byIdentity as $identity => $data) {
            $userId = $identityMap[$identity] ?? null;

            if ($userId === null) {
                continue;
            }

            $evidence[] = new OwnershipEvidence(
                userId: $userId,
                rule: OwnershipRule::BlameConcentration,
                score: (float) $data['lines'],
                paths: array_values(array_unique($data['paths'])),
                detail: ['identity' => $identity, 'lines' => $data['lines']],
            );
        }

        return $evidence;
    }

    /**
     * @param  array{author: ?string, author_email: ?string, lines: int}  $entry
     */
    private function identityOf(array $entry): ?string
    {
        $handle = $entry['author'] ?? null;

        if (is_string($handle) && $handle !== '') {
            return $handle;
        }

        $email = $entry['author_email'] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }
}
