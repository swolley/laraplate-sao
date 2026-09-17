<?php

declare(strict_types=1);

use Modules\Core\Authorization\PermissionManifest;
use Modules\SAO\Authorization\SAOPermissions;
use Modules\SAO\Models\Ticket;

/**
 * `sao_tickets.assign` was seeded but never read: no handler, no policy method,
 * no gate. A migration used to delete the row; the declaration that produced it
 * is gone instead, so there is nothing left to delete and nothing to re-create
 * on a fresh database. What has to stay true is that the declaration never
 * comes back, which is what this file asserts now.
 */
it('does not declare assign on tickets', function (): void {
    expect(SAOPermissions::operations()[Ticket::class] ?? [])->not->toContain('assign');
});

it('declares the ticket operations that do have a handler', function (): void {
    expect(SAOPermissions::operations()[Ticket::class] ?? [])
        ->toContain('transition')
        ->toContain('transition_override')
        ->toContain('close');
});

it('generates no assign permission for any SAO model', function (): void {
    $names = app(PermissionManifest::class)->namesFor('SAO');

    expect(array_filter($names, static fn (string $name): bool => str_ends_with($name, '.assign')))->toBe([]);
});
