<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Core\Events\AiTextGenerationRequested;
use Modules\SAO\Contracts\SuggestionTextGenerator;

/**
 * Asks for AI text through Core's {@see AiTextGenerationRequested} event rather
 * than calling the AI module directly. It dispatches the request and returns
 * whatever a listener filled in, or an empty string when nothing is listening —
 * which is how the AI stays entirely optional and SAO keeps no dependency on the
 * AI module.
 */
final readonly class EventTextGenerator implements SuggestionTextGenerator
{
    public function __construct(private Dispatcher $events) {}

    public function generate(string $prompt): string
    {
        $request = new AiTextGenerationRequested($prompt, 'sao.ownership_suggestion');

        $this->events->dispatch($request);

        return $request->response ?? '';
    }
}
