<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Core\Events\AiTextGenerationRequested;
use Modules\SAO\Contracts\SuggestionPhraser;
use Modules\SAO\Contracts\SuggestionTextGenerator;
use Modules\SAO\Services\AiSuggestionPhraser;
use Modules\SAO\Services\EventTextGenerator;

test('the SAO provider binds the event-driven generator and the AI phraser', function (): void {
    expect(app(SuggestionTextGenerator::class))->toBeInstanceOf(EventTextGenerator::class)
        ->and(app(SuggestionPhraser::class))->toBeInstanceOf(AiSuggestionPhraser::class);
});

test('with no listener the generator returns an empty string', function (): void {
    expect((new EventTextGenerator(app('events')))->generate('rewrite this'))->toBe('');
});

test('a listener that fulfils the request has its response returned', function (): void {
    app('events')->listen(AiTextGenerationRequested::class, static function (AiTextGenerationRequested $event): void {
        $event->fulfill('a natural sentence about ' . $event->purpose);
    });

    expect((new EventTextGenerator(app('events')))->generate('rewrite this'))
        ->toBe('a natural sentence about sao.ownership_suggestion');
});

test('the request carries the ownership purpose', function (): void {
    Event::fake([AiTextGenerationRequested::class]);

    (new EventTextGenerator(app('events')))->generate('rewrite this');

    Event::assertDispatched(
        AiTextGenerationRequested::class,
        static fn (AiTextGenerationRequested $event): bool => $event->purpose === 'sao.ownership_suggestion' && $event->prompt === 'rewrite this',
    );
});
