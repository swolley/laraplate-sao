<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Enums\SyncDirection;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::ProjectBindings->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('project_id')->constrained(SAOTables::Projects->value)->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained(SAOTables::Connections->value)->cascadeOnDelete();
            $table->enum('capability', Capability::values());
            $table->string('remote_identifier')->nullable()->comment('The remote object this binding targets (e.g. a Redmine project id)');
            $table->enum('sync_direction', SyncDirection::values())->default(SyncDirection::Disabled->value);
            $table->json('status_map')->nullable()->comment('Remote status name → canonical category');
            $table->json('priority_map')->nullable();
            $table->json('config')->nullable()->comment('Binding-scoped non-secret configuration');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique(
                ['project_id', 'connection_id', 'capability', 'remote_identifier'],
                "{$table_name}_scope_UN",
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::ProjectBindings->value);
    }
};
