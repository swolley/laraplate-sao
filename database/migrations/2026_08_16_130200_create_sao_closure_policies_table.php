<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\ClosureAction;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::ClosurePolicies->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('project_id')
                ->constrained(SAOTables::Projects->value, 'id', "{$table_name}_project_FK")
                ->cascadeOnDelete();
            $table->string('name');
            $table->json('conditions')->comment('Ordered list of {key, config} combined with AND');
            $table->enum('action', ClosureAction::values())->default(ClosureAction::Propose->value);
            $table->boolean('is_active')->default(true);

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique(['project_id', 'name'], "{$table_name}_project_name_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::ClosurePolicies->value);
    }
};
