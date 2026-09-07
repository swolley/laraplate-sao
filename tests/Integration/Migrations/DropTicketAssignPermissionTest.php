<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\CoreTables;

uses(RefreshDatabase::class);

/**
 * `sao_tickets.assign` was seeded but never read: no handler, no policy method,
 * no gate. The migration drops it so the role screen stops offering a rule that
 * governs nothing.
 */
function insertAssignPermission(string $name): int
{
    return (int) DB::table(CoreTables::Permissions->value)->insertGetId([
        'name' => $name,
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function runTicketAssignPermissionDrop(): void
{
    $migration = require module_path('SAO', 'database/migrations/2026_09_07_000000_drop_unused_ticket_assign_permission.php');

    $migration->up();
}

it('drops the ticket assign permission', function (): void {
    $id = insertAssignPermission('default.sao_tickets.assign');

    runTicketAssignPermissionDrop();

    expect(DB::table(CoreTables::Permissions->value)->where('id', $id)->exists())->toBeFalse();
});

it('takes the grants and ACLs hanging off it', function (): void {
    $role_id = DB::table(CoreTables::Roles->value)->insertGetId([
        'name' => 'sao_assign_role',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $id = insertAssignPermission('default.sao_tickets.assign');

    DB::table(CoreTables::RoleHasPermissions->value)->insert([
        'permission_id' => $id,
        'role_id' => $role_id,
    ]);

    $acl_id = DB::table(CoreTables::Acls->value)->insertGetId([
        'permission_id' => $id,
        'role_id' => $role_id,
        'unrestricted' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    runTicketAssignPermissionDrop();

    expect(DB::table(CoreTables::RoleHasPermissions->value)->where('permission_id', $id)->exists())->toBeFalse()
        ->and(DB::table(CoreTables::Acls->value)->where('id', $acl_id)->exists())->toBeFalse();
});

it('leaves the live ticket verbs alone', function (): void {
    $survivors = [
        insertAssignPermission('default.sao_tickets.transition'),
        insertAssignPermission('default.sao_tickets.select'),
        insertAssignPermission('default.sao_tickets.update'),
    ];

    runTicketAssignPermissionDrop();

    expect(DB::table(CoreTables::Permissions->value)->whereIn('id', $survivors)->count())->toBe(3);
});

/**
 * Only `sao_tickets` ever declared the verb. Another table spelling it is
 * someone else's decision to unwind.
 */
it('leaves assign on another table alone', function (): void {
    $foreign_id = insertAssignPermission('default.cms_contents.assign');

    runTicketAssignPermissionDrop();

    expect(DB::table(CoreTables::Permissions->value)->where('id', $foreign_id)->exists())->toBeTrue();
});
