<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::SavedFilters->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained(CoreTables::Users->value, 'id', "{$table_name}_user_FK")
                ->cascadeOnDelete();
            $table->foreignId('project_id')
                ->nullable()
                ->comment('Null means the filter spans every project the user can see')
                ->constrained(SAOTables::Projects->value, 'id', "{$table_name}_project_FK")
                ->cascadeOnDelete();
            $table->string('name');
            $table->json('criteria');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->index('user_id', "{$table_name}_user_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::SavedFilters->value);
    }
};
