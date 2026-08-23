<?php

declare(strict_types=1);

namespace Modules\SAO\Attribution;

use Modules\SAO\Enums\ChangeRefRelation;

/**
 * Pulls ticket keys out of code text and classifies each as a fix or a mention.
 *
 * A ticket key is a project prefix and number (`SAO-123`). A configured closing
 * verb immediately before a key — `fixes SAO-1`, `closes SAO-1`, `resolved:
 * SAO-1`, optionally with a leading `#` — marks it a fix; every other key is a
 * mention. When the same key appears both ways, the fix wins. The verb list is
 * configurable via `sao.attribution.closing_verbs`.
 */
final class TicketReferenceExtractor
{
    private const string KEY_PATTERN = '[A-Z][A-Z0-9]{1,9}-\d+';

    /**
     * @return list<TicketReference>
     */
    public function extract(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $fixed = $this->fixedKeys($text);
        $all = $this->allKeys($text);

        $references = [];

        foreach ($all as $key) {
            $relation = isset($fixed[$key]) ? ChangeRefRelation::Fixes : ChangeRefRelation::Mentions;
            $references[$key] = new TicketReference($key, $relation);
        }

        return array_values($references);
    }

    /**
     * Keys preceded by a closing verb, as a set (key => true).
     *
     * @return array<string, true>
     */
    private function fixedKeys(string $text): array
    {
        $verbs = implode('|', array_map(preg_quote(...), $this->closingVerbs()));
        $pattern = '/\b(?:' . $verbs . ')\b[\s:]+#?(' . self::KEY_PATTERN . ')/i';

        preg_match_all($pattern, $text, $matches);

        $keys = [];

        foreach ($matches[1] as $key) {
            $keys[mb_strtoupper($key)] = true;
        }

        return $keys;
    }

    /**
     * Every ticket key in the text, in first-seen order.
     *
     * @return list<string>
     */
    private function allKeys(string $text): array
    {
        preg_match_all('/\b(' . self::KEY_PATTERN . ')\b/', $text, $matches);

        $keys = [];

        foreach ($matches[1] as $key) {
            $keys[mb_strtoupper($key)] = true;
        }

        return array_keys($keys);
    }

    /**
     * @return list<string>
     */
    private function closingVerbs(): array
    {
        /** @var list<string> $verbs */
        $verbs = (array) config('sao.attribution.closing_verbs', [
            'fix', 'fixes', 'fixed',
            'close', 'closes', 'closed',
            'resolve', 'resolves', 'resolved',
        ]);

        return $verbs;
    }
}
