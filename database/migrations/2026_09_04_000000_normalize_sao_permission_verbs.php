<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Spatie\Permission\PermissionRegistrar;

/**
 * Collapse SAO's `view`/`create` permissions onto Core's `select`/`insert`.
 *
 * SAO seeded its CRUD permissions with the Filament policy vocabulary while the
 * rest of the stack authorizes with Core's: `select` is what
 * {@see Modules\Core\Services\Crud\CrudService} checks for every read (lists,
 * details, history, trees alike) and `insert` for every write. The result was
 * two read anchors on one table — an ACL configured on `sao_tickets.view` left
 * `/app` unfiltered, and one configured on `sao_tickets.select` left the
 * Filament board and the ticket search unfiltered.
 *
 * `view` rows are merged onto `select` and `create` rows onto `insert`, moving
 * grants and ACLs before the legacy row is dropped. `update` and `delete` need
 * nothing: SAO spelled them the way Core does, so both spellings were always the
 * same row.
 */
return new class extends Migration
{
    /**
     * Legacy operation segment => the Core verb that already means it.
     */
    private const array RENAMES = [
        'view' => 'select',
        'create' => 'insert',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /** @var ConnectionInterface $connection */
        $connection = app('db')->connection();

        $permissions_table = (string) config('permission.table_names.permissions', CoreTables::Permissions->value);

        if (! Schema::hasTable($permissions_table) || ! Schema::hasColumn($permissions_table, 'table_name')) {
            return;
        }

        $renamed = false;

        foreach (self::RENAMES as $legacy_operation => $target_operation) {
            $legacy_rows = $connection->table($permissions_table)
                ->where('name', 'like', '%.' . $legacy_operation)
                ->get(['id', 'name', 'guard_name', 'table_name']);

            foreach ($legacy_rows as $legacy) {
                // Only SAO ever used these verbs, and only SAO's rows are this
                // migration's to move: another module's `view` would be its own
                // decision to unwind.
                if (! str_starts_with((string) $legacy->table_name, 'sao_')) {
                    continue;
                }

                $name = (string) $legacy->name;
                $target_name = mb_substr($name, 0, mb_strlen($name) - mb_strlen($legacy_operation)) . $target_operation;

                $target_id = $connection->table($permissions_table)
                    ->where('name', $target_name)
                    ->where('guard_name', $legacy->guard_name)
                    ->value('id');

                if ($target_id === null) {
                    $connection->table($permissions_table)
                        ->where('id', $legacy->id)
                        ->update(['name' => $target_name]);

                    $renamed = true;

                    continue;
                }

                $this->mergeAssignments($connection, (int) $legacy->id, (int) $target_id);

                $connection->table($permissions_table)->where('id', $legacy->id)->delete();

                $renamed = true;
            }
        }

        if ($renamed) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: grants merged onto the surviving permission cannot be split
        // back into the two spellings they came from.
    }

    /**
     * Move every grant and every ACL on the legacy permission onto the survivor.
     */
    private function mergeAssignments(ConnectionInterface $connection, int $legacy_id, int $target_id): void
    {
        $permission_key = (string) (config('permission.column_names.permission_pivot_key') ?: 'permission_id');

        $pivot_tables = [
            (string) config('permission.table_names.role_has_permissions', CoreTables::RoleHasPermissions->value),
            (string) config('permission.table_names.model_has_permissions', CoreTables::ModelHasPermissions->value),
        ];

        foreach ($pivot_tables as $pivot_table) {
            if (! Schema::hasTable($pivot_table)) {
                continue;
            }

            $this->repointPivot($connection, $pivot_table, $permission_key, $legacy_id, $target_id);
        }

        $acls_table = CoreTables::Acls->value;

        if (Schema::hasTable($acls_table)) {
            $this->repointAcls($connection, $acls_table, $legacy_id, $target_id);
        }
    }

    /**
     * The pivots carry a composite key (role, or model type + id, plus the team key
     * when teams are on), so a grant is moved only when the survivor does not hold
     * the same one already; otherwise the legacy row is simply dropped.
     */
    private function repointPivot(
        ConnectionInterface $connection,
        string $pivot_table,
        string $permission_key,
        int $legacy_id,
        int $target_id,
    ): void {
        $rows = $connection->table($pivot_table)->where($permission_key, $legacy_id)->get();

        foreach ($rows as $row) {
            /** @var array<string,mixed> $holder */
            $holder = (array) $row;
            unset($holder[$permission_key]);

            $legacy_row = $connection->table($pivot_table)->where($permission_key, $legacy_id);
            $survivor_row = $connection->table($pivot_table)->where($permission_key, $target_id);

            foreach ($holder as $column => $value) {
                $legacy_row->where($column, $value);
                $survivor_row->where($column, $value);
            }

            if ($survivor_row->exists()) {
                $legacy_row->delete();

                continue;
            }

            $legacy_row->update([$permission_key => $target_id]);
        }
    }

    /**
     * ACLs have no uniqueness to collide on: an ACL is a filter set, and two of
     * them on the same permission are evaluated together by priority. They move
     * across whole, so a rule written against the old verb keeps restricting the
     * rows it was written to restrict.
     */
    private function repointAcls(
        ConnectionInterface $connection,
        string $acls_table,
        int $legacy_id,
        int $target_id,
    ): void {
        $connection->table($acls_table)
            ->where('permission_id', $legacy_id)
            ->update(['permission_id' => $target_id]);
    }
};
