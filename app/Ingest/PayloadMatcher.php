<?php

declare(strict_types=1);

namespace Modules\SAO\Ingest;

use Modules\SAO\Models\SourceProfile;

/**
 * Decides whether a source profile applies to a payload: every matcher must
 * pass. A matcher is `{path, operator, value?}` over a dot-path (`data_get`),
 * with operators `equals`, `exists` and `contains`.
 */
final class PayloadMatcher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function matches(SourceProfile $profile, array $payload): bool
    {
        foreach ($profile->matchers as $matcher) {
            if (! $this->matcherPasses($matcher, $payload)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{path?: string, operator?: string, value?: mixed}  $matcher
     * @param  array<string, mixed>  $payload
     */
    private function matcherPasses(array $matcher, array $payload): bool
    {
        $actual = data_get($payload, $matcher['path'] ?? '');
        $operator = $matcher['operator'] ?? 'equals';
        $expected = $matcher['value'] ?? null;

        return match ($operator) {
            'exists' => $actual !== null,
            'contains' => $this->contains($actual, $expected),
            default => $actual === $expected,
        };
    }

    private function contains(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            return in_array($expected, $actual, true);
        }

        return is_string($actual) && is_string($expected) && $expected !== '' && str_contains($actual, $expected);
    }
}
