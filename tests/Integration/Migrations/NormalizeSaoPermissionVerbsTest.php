<?php

declare(strict_types=1);

use Modules\Core\Casts\ActionEnum;
use Modules\Core\Support\PermissionName;
use Modules\SAO\Models\Ticket;

/**
 * SAO used to seed its CRUD permissions with the Filament policy vocabulary, so
 * `sao_tickets.view` sat next to the `sao_tickets.select` every other read path
 * authorizes with, and `create` next to `insert`. A migration used to collapse
 * the first onto the second for each pair; it is gone, because the vocabulary
 * itself is now the single source: permission names come from ActionEnum, which
 * has no `view` and no `create` to generate.
 *
 * These assertions are what keeps the old spelling from coming back. The
 * migration's other half — carrying grants and ACLs from the legacy row onto
 * the survivor — has no subject any more: on a fresh database the legacy row is
 * never written.
 */
it('has no legacy verb in the vocabulary that generates permission names', function (): void {
    $verbs = array_map(static fn (ActionEnum $case): string => $case->value, ActionEnum::cases());

    expect($verbs)->not->toContain('view')
        ->and($verbs)->not->toContain('create');
});

it('spells the read and write verbs select and insert', function (): void {
    $verbs = array_map(static fn (ActionEnum $case): string => $case->value, ActionEnum::cases());

    expect($verbs)->toContain('select')
        ->and($verbs)->toContain('insert');
});

it('names a ticket read permission with select', function (): void {
    expect(PermissionName::forClass(Ticket::class, ActionEnum::Select->value))
        ->toEndWith('sao_tickets.select');
});
