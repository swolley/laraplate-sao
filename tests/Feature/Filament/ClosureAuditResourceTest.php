<?php

declare(strict_types=1);

use Modules\SAO\Filament\Resources\ClosureAudits\ClosureAuditResource;
use Modules\SAO\Models\ClosureAudit;

test('the closure audit resource is bound to its model under the SAO group', function (): void {
    expect(ClosureAuditResource::getModel())->toBe(ClosureAudit::class)
        ->and(ClosureAuditResource::getNavigationGroup())->toBe('SAO')
        ->and(ClosureAuditResource::getSlug())->toStartWith('sao/');
});

test('the closure audit resource is read-only: list and view, no create or edit', function (): void {
    expect(array_keys(ClosureAuditResource::getPages()))->toBe(['index', 'view'])
        ->and(ClosureAuditResource::canCreate())->toBeFalse();
});

test('the closure audit list offers no create action', function (): void {
    $path = dirname(__DIR__, 3) . '/app/Filament/Resources/ClosureAudits/Pages/ListClosureAudits.php';
    $contents = (string) file_get_contents($path);

    expect($contents)->toContain('getHeaderActions')
        ->and($contents)->toContain('return [];');
});
