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
