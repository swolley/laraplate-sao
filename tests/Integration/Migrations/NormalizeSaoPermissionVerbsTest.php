<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\CoreTables;

uses(RefreshDatabase::class);

/**
 * SAO used to seed its CRUD permissions with the Filament policy vocabulary,
 * so `sao_tickets.view` sat next to the `sao_tickets.select` every other read
 * path authorizes with. The migration collapses the first onto the second.
 */
function insertSaoPermission(string $name): int
{
    return (int) DB::table(CoreTables::Permissions->value)->insertGetId([
        'name' => $name,
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function runSaoPermissionVerbNormalization(): void
{
    $migration = require module_path('SAO', 'database/migrations/2026_09_04_000000_normalize_sao_permission_verbs.php');

    $migration->up();
}

it('renames a view permission onto select when no select exists', function (): void {
    $legacy_id = insertSaoPermission('default.sao_widgets.view');

    runSaoPermissionVerbNormalization();

    expect(DB::table(CoreTables::Permissions->value)->where('id', $legacy_id)->value('name'))
        ->toBe('default.sao_widgets.select');
});

it('maps create onto insert', function (): void {
    $legacy_id = insertSaoPermission('default.sao_widgets.create');

    runSaoPermissionVerbNormalization();

    expect(DB::table(CoreTables::Permissions->value)->where('id', $legacy_id)->value('name'))
        ->toBe('default.sao_widgets.insert');
});

it('merges the view permission onto select, carrying grants and ACLs across', function (): void {
    $role_id = DB::table(CoreTables::Roles->value)->insertGetId([
        'name' => 'sao_verbs_role',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $survivor_id = insertSaoPermission('default.sao_tickets.select');
    $legacy_id = insertSaoPermission('default.sao_tickets.view');

    DB::table(CoreTables::RoleHasPermissions->value)->insert([
        'permission_id' => $legacy_id,
        'role_id' => $role_id,
    ]);

    $acl_id = DB::table(CoreTables::Acls->value)->insertGetId([
        'permission_id' => $legacy_id,
        'role_id' => $role_id,
        'unrestricted' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    runSaoPermissionVerbNormalization();

    expect(DB::table(CoreTables::Permissions->value)->where('id', $legacy_id)->exists())->toBeFalse()
        ->and(DB::table(CoreTables::RoleHasPermissions->value)
            ->where('permission_id', $survivor_id)
            ->where('role_id', $role_id)
            ->exists())->toBeTrue()
        ->and(DB::table(CoreTables::Acls->value)->where('id', $acl_id)->value('permission_id'))
        ->toBe($survivor_id);
});

/**
 * A grant the survivor already holds cannot be moved onto it without colliding
 * on the pivot's composite key, so the legacy row is dropped instead.
 */
it('drops a duplicate grant instead of colliding on the pivot key', function (): void {
    $role_id = DB::table(CoreTables::Roles->value)->insertGetId([
        'name' => 'sao_verbs_duplicate_role',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $survivor_id = insertSaoPermission('default.sao_tickets.select');
    $legacy_id = insertSaoPermission('default.sao_tickets.view');

    DB::table(CoreTables::RoleHasPermissions->value)->insert([
        ['permission_id' => $survivor_id, 'role_id' => $role_id],
        ['permission_id' => $legacy_id, 'role_id' => $role_id],
    ]);

    runSaoPermissionVerbNormalization();

    expect(DB::table(CoreTables::RoleHasPermissions->value)->where('role_id', $role_id)->count())->toBe(1)
        ->and(DB::table(CoreTables::RoleHasPermissions->value)
            ->where('permission_id', $survivor_id)
            ->where('role_id', $role_id)
            ->exists())->toBeTrue();
});

/**
 * The migration is SAO's to run, and only SAO ever spelled a read permission
 * `view`. Another module's row is left where it is.
 */
it('leaves a non-SAO table alone', function (): void {
    $foreign_id = insertSaoPermission('default.cms_contents.view');

    runSaoPermissionVerbNormalization();

    expect(DB::table(CoreTables::Permissions->value)->where('id', $foreign_id)->value('name'))
        ->toBe('default.cms_contents.view');
});
