<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\SAO\Contracts\SuggestionPhraser;
use Modules\SAO\Enums\OwnershipRule;
use Modules\SAO\Models\OwnershipSuggestion;
use Modules\SAO\Services\TemplateSuggestionPhraser;

uses(RefreshDatabase::class);

test('the default phraser is bound and is the deterministic template', function (): void {
    expect(app(SuggestionPhraser::class))->toBeInstanceOf(TemplateSuggestionPhraser::class);
});

test('it phrases a suggestion factually from its persisted fields', function (): void {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    $suggestion = OwnershipSuggestion::factory()->create([
        'suggested_user_id' => $user->id,
        'rule' => OwnershipRule::Codeowners,
        'score' => 2.0,
        'evidence' => ['paths' => ['app/Billing/Invoice.php', 'app/Billing/Payment.php']],
    ]);

    $text = (new TemplateSuggestionPhraser())->phrase($suggestion);

    expect($text)->toContain('Ada Lovelace')
        ->toContain('CODEOWNERS entry')
        ->toContain('app/Billing/Invoice.php')
        ->toContain('score 2')
        ->toContain('Review before assigning');
});

test('more than three evidence paths are summarized', function (): void {
    $user = User::factory()->create(['name' => 'Grace']);
    $suggestion = OwnershipSuggestion::factory()->create([
        'suggested_user_id' => $user->id,
        'rule' => OwnershipRule::BlameConcentration,
        'score' => 40.0,
        'evidence' => ['paths' => ['a.php', 'b.php', 'c.php', 'd.php', 'e.php']],
    ]);

    expect((new TemplateSuggestionPhraser())->phrase($suggestion))->toContain('(+2 more)');
});

test('a suggestion without a user reports that none could be made', function (): void {
    $suggestion = OwnershipSuggestion::factory()->create([
        'suggested_user_id' => null,
        'evidence' => ['paths' => []],
    ]);

    expect((new TemplateSuggestionPhraser())->phrase($suggestion))
        ->toBe('No owner could be suggested from the available code evidence.');
});
