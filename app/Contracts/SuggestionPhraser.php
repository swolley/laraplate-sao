<?php

declare(strict_types=1);

namespace Modules\SAO\Contracts;

use Modules\SAO\Models\OwnershipSuggestion;

/**
 * Turns an ownership suggestion into human-facing text.
 *
 * The default is deterministic and factual; phase 8 may bind an AI-backed
 * implementation that rephrases it more naturally. Either way the phraser only
 * describes the persisted proposal — it never invents ownership or changes who
 * was suggested (D14).
 */
interface SuggestionPhraser
{
    public function phrase(OwnershipSuggestion $suggestion): string;
}
