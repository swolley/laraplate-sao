<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\SAO\Exceptions\OwnershipSuggestionNotApplicableException;
use Modules\SAO\Models\OwnershipSuggestion;
use Modules\SAO\Services\OwnershipSuggestionApplier;

uses(RefreshDatabase::class);

test('accepting a suggestion assigns the suggested owner to the ticket', function (): void {
    $user = User::factory()->create();
    $suggestion = OwnershipSuggestion::factory()->create(['suggested_user_id' => $user->id]);

    expect($suggestion->ticket->assignee_id)->not->toBe($user->id);

    $ticket = app(OwnershipSuggestionApplier::class)->apply($suggestion);

    expect($ticket->assignee_id)->toBe($user->id)
        ->and($suggestion->ticket->fresh()->assignee_id)->toBe($user->id);
});

test('a suggestion without a suggested user cannot be applied', function (): void {
    $suggestion = OwnershipSuggestion::factory()->create(['suggested_user_id' => null]);

    expect(fn (): mixed => app(OwnershipSuggestionApplier::class)->apply($suggestion))
        ->toThrow(OwnershipSuggestionNotApplicableException::class);
});
