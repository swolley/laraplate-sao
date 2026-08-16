<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Contracts\SuggestionPhraser;
use Modules\SAO\Contracts\SuggestionTextGenerator;
use Modules\SAO\Models\OwnershipSuggestion;
use Throwable;

/**
 * Rephrases the deterministic suggestion text more naturally through a text
 * generator, without ever changing what it says. The factual text is the source
 * of truth: it goes into the prompt, and the model is asked only to rewrite it.
 *
 * Two guards keep the AI from inventing ownership (D14): if the generator fails,
 * returns nothing, or drops the suggested user's name from its output, the
 * deterministic text is used instead. A suggestion with no user is never sent to
 * the generator at all.
 */
final readonly class AiSuggestionPhraser implements SuggestionPhraser
{
    public function __construct(
        private TemplateSuggestionPhraser $factual,
        private SuggestionTextGenerator $generator,
    ) {}

    public function phrase(OwnershipSuggestion $suggestion): string
    {
        $factual = $this->factual->phrase($suggestion);
        $name = $suggestion->suggestedUser?->name;

        if ($name === null || $name === '') {
            return $factual;
        }

        try {
            $rephrased = trim($this->generator->generate($this->prompt($factual, $name)));
        } catch (Throwable) {
            return $factual;
        }

        // The rewrite must still name the same owner; otherwise it has drifted
        // from the evidence and the factual text stands.
        if ($rephrased === '' || ! str_contains($rephrased, $name)) {
            return $factual;
        }

        return $rephrased;
    }

    private function prompt(string $factual, string $name): string
    {
        return <<<PROMPT
        Rewrite the following ownership suggestion as one natural, concise sentence.
        Do not change the facts, and keep the exact name "{$name}". Do not invent a
        different owner or add claims not present. Reply with the sentence only.

        {$factual}
        PROMPT;
    }
}
