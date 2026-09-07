<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Spatie\Permission\PermissionRegistrar;

/**
 * Drop `sao_tickets.assign`, a permission nothing ever read.
 *
 * It was declared with `transition` and `transition_override` as one of the
 * operations the domain distinguishes, but no handler, policy method or gate was
 * ever written for it. {@see Modules\Core\Services\Crud\DomainActionDispatcher}
 * resolves the handler before authorizing, so a request for an unregistered
 * action is a 404 and the permission is never reached — while an administrator
 * reading the role screen would take it for the rule on who may assign a ticket.
 * Assigning is a write to `assignee_id`, governed by `update` like every other
 * column, and the one sanctioned assignment path is accepting an ownership
 * suggestion, which carries `sao_ownership_suggestions.accept`.
 *
 * `permission:refresh` cannot clean this up: it prunes only the verbs it
 * generates itself, precisely so a name typed into
 * `sao_workflow_transitions.required_permission` is never deleted underneath a
 * transition. A domain verb it does not generate has to be dropped explicitly.
 *
 * Grants and ACLs on the row go with it through their cascading foreign keys.
 * Neither restricted anything: an ACL is applied by permission name, and no code
 * ever asked for this one.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions_table = (string) config('permission.table_names.permissions', CoreTables::Permissions->value);

        if (! Schema::hasTable($permissions_table) || ! Schema::hasColumn($permissions_table, 'table_name')) {
            return;
        }

        $connection = app('db')->connection();

        // Matched on the table and the trailing operation rather than on the whole
        // name: the connection segment differs per installation, and a `LIKE`
        // pattern would read the underscores in `sao_tickets` as wildcards.
        $ids = $connection->table($permissions_table)
            ->where('table_name', 'sao_tickets')
            ->get(['id', 'name'])
            ->filter(static fn (object $permission): bool => str_ends_with((string) $permission->name, '.assign'))
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return;
        }

        $connection->table($permissions_table)->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible on purpose: recreating the row would restore a permission
        // with no consumer, and the grants that hung off it are gone with it.
    }
};
