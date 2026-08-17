<?php

declare(strict_types=1);

use Modules\SAO\Filament\Resources\OwnershipSuggestions\OwnershipSuggestionResource;
use Modules\SAO\Models\OwnershipSuggestion;

test('the ownership suggestion resource is bound to its model under the SAO group', function (): void {
    expect(OwnershipSuggestionResource::getModel())->toBe(OwnershipSuggestion::class)
        ->and(OwnershipSuggestionResource::getNavigationGroup())->toBe('SAO')
        ->and(OwnershipSuggestionResource::getSlug())->toStartWith('sao/');
});

test('the ownership suggestion resource is read-only: list and view, no create or edit', function (): void {
    expect(array_keys(OwnershipSuggestionResource::getPages()))->toBe(['index', 'view'])
        ->and(OwnershipSuggestionResource::canCreate())->toBeFalse();
});

test('the table wires a manual accept action gated on a suggested user', function (): void {
    $table = (string) file_get_contents(
        dirname(__DIR__, 3) . '/app/Filament/Resources/OwnershipSuggestions/Tables/OwnershipSuggestionsTable.php',
    );

    // The accept action assigns the suggested owner (through the tested applier)
    // and is hidden when there is no user to assign (D14 — never automatic).
    expect($table)->toContain("Action::make('acceptSuggestion')")
        ->and($table)->toContain('OwnershipSuggestionApplier')
        ->and($table)->toContain('suggested_user_id !== null');
});
