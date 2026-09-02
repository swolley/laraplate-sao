<?php

declare(strict_types=1);

namespace Modules\SAO\Database\Seeders;

use Modules\Core\Authorization\PermissionManifest;
use Modules\Core\Models\Permission;
use Modules\Core\Overrides\Seeder;

/**
 * Materializes this module's slice of the permission manifest.
 *
 * The names are declared once in {@see \Modules\SAO\Authorization\SAOPermissions}
 * and created by `permission:refresh`, which runs before every module seeder in
 * the graph. This seeder keeps SAO seeded in isolation self-sufficient, which is
 * what the module test suites rely on.
 */
final class SAOPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permission_model = new Permission;

        if (! $permission_model->getConnection()->getSchemaBuilder()->hasTable($permission_model->getTable())) {
            return;
        }

        foreach (app(PermissionManifest::class)->namesFor('SAO') as $name) {
            $permission_model->newQuery()->firstOrCreate(['name' => $name]);
        }

        $this->command?->line('    - SAO domain permissions <fg=green>updated</>');
    }
}
