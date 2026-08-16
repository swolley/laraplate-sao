<?php

declare(strict_types=1);

namespace Modules\SAO\Contracts;

/**
 * A one-shot text generator, the narrow seam the AI module fills at phase 8.
 *
 * SAO depends on this rather than on the AI module directly, so an AI-backed
 * phraser can be activated by binding an implementation without SAO taking a
 * hard dependency on AI. Implementations should return the generated text, or
 * an empty string when they cannot produce one.
 */
interface SuggestionTextGenerator
{
    public function generate(string $prompt): string;
}
