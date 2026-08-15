<?php

declare(strict_types=1);

use Modules\SAO\Enums\SyncDirection;

test('sync directions are the four configurable modes', function (): void {
    expect(SyncDirection::values())->toEqualCanonicalizing(['inbound', 'outbound', 'bidirectional', 'disabled'])
        ->and(SyncDirection::from('outbound'))->toBe(SyncDirection::Outbound)
        ->and(SyncDirection::from('disabled'))->toBe(SyncDirection::Disabled);
});

test('an unknown direction throws', function (): void {
    expect(fn (): SyncDirection => SyncDirection::from('sideways'))->toThrow(ValueError::class);
});

test('sync direction exposes an in: validation rule', function (): void {
    expect(SyncDirection::validationRule())->toStartWith('in:');
});
