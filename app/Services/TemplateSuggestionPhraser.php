<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Contracts\SuggestionPhraser;
use Modules\SAO\Models\OwnershipSuggestion;

/**
 * The deterministic default phraser: it states the suggestion from its persisted
 * fields alone. No model, no invention — the same suggestion always yields the
 * same sentence, which is what makes it safe to show before any AI is involved.
 */
final class TemplateSuggestionPhraser implements SuggestionPhraser
{
    public function phrase(OwnershipSuggestion $suggestion): string
    {
        $name = $suggestion->suggestedUser?->name;

        if ($name === null || $name === '') {
            return 'No owner could be suggested from the available code evidence.';
        }

        $paths = $this->paths($suggestion);
        $where = $paths === [] ? '' : ' on ' . $this->joinPaths($paths);

        return sprintf(
            'Suggested owner: %s, from %s%s (score %s). Review before assigning.',
            $name,
            $suggestion->rule->label(),
            $where,
            $this->formatScore($suggestion->score),
        );
    }

    /**
     * @return list<string>
     */
    private function paths(OwnershipSuggestion $suggestion): array
    {
        $paths = $suggestion->evidence['paths'] ?? [];

        return is_array($paths)
            ? array_values(array_map(static fn (mixed $path): string => (string) $path, $paths))
            : [];
    }

    /**
     * @param  list<string>  $paths
     */
    private function joinPaths(array $paths): string
    {
        $shown = array_slice($paths, 0, 3);
        $text = implode(', ', $shown);

        return count($paths) > 3 ? $text . ' (+' . (count($paths) - 3) . ' more)' : $text;
    }

    private function formatScore(float $score): string
    {
        return rtrim(rtrim(number_format($score, 2, '.', ''), '0'), '.');
    }
}
