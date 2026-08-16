<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\SAO\Contracts\SuggestionTextGenerator;
use Modules\SAO\Enums\OwnershipRule;
use Modules\SAO\Models\OwnershipSuggestion;
use Modules\SAO\Services\AiSuggestionPhraser;
use Modules\SAO\Services\TemplateSuggestionPhraser;

uses(RefreshDatabase::class);

function suggestionFor(User $user): OwnershipSuggestion
{
    return OwnershipSuggestion::factory()->create([
        'suggested_user_id' => $user->id,
        'rule' => OwnershipRule::Codeowners,
        'score' => 2.0,
        'evidence' => ['paths' => ['app/Billing/Invoice.php']],
    ]);
}

test('it returns the AI rewrite when it keeps the suggested name', function (): void {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    $generator = Mockery::mock(SuggestionTextGenerator::class);
    $generator->shouldReceive('generate')->once()->andReturn('Ada Lovelace looks like the owner here — please review.');

    $phraser = new AiSuggestionPhraser(new TemplateSuggestionPhraser(), $generator);

    expect($phraser->phrase(suggestionFor($user)))->toBe('Ada Lovelace looks like the owner here — please review.');
});

test('it passes the factual text and the exact name to the generator', function (): void {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    $generator = Mockery::mock(SuggestionTextGenerator::class);
    $generator->shouldReceive('generate')
        ->once()
        ->with(Mockery::on(static fn (string $prompt): bool => str_contains($prompt, 'Ada Lovelace') && str_contains($prompt, 'CODEOWNERS entry')))
        ->andReturn('Ada Lovelace is the suggested owner.');

    (new AiSuggestionPhraser(new TemplateSuggestionPhraser(), $generator))->phrase(suggestionFor($user));
});

test('it falls back to the factual text when the rewrite drops the owner name', function (): void {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    $generator = Mockery::mock(SuggestionTextGenerator::class);
    $generator->shouldReceive('generate')->andReturn('Someone else entirely should own this.');

    $phraser = new AiSuggestionPhraser(new TemplateSuggestionPhraser(), $generator);

    expect($phraser->phrase(suggestionFor($user)))->toContain('Ada Lovelace')
        ->toContain('CODEOWNERS entry');
});

test('it falls back to the factual text when the generator throws', function (): void {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    $generator = Mockery::mock(SuggestionTextGenerator::class);
    $generator->shouldReceive('generate')->andThrow(new RuntimeException('LLM down'));

    $phraser = new AiSuggestionPhraser(new TemplateSuggestionPhraser(), $generator);

    expect($phraser->phrase(suggestionFor($user)))->toContain('Ada Lovelace');
});

test('a suggestion with no user is never sent to the generator', function (): void {
    $suggestion = OwnershipSuggestion::factory()->create(['suggested_user_id' => null, 'evidence' => ['paths' => []]]);
    $generator = Mockery::mock(SuggestionTextGenerator::class);
    $generator->shouldNotReceive('generate');

    $phraser = new AiSuggestionPhraser(new TemplateSuggestionPhraser(), $generator);

    expect($phraser->phrase($suggestion))->toBe('No owner could be suggested from the available code evidence.');
});
