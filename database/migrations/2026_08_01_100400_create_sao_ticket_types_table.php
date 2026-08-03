<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::TicketTypes->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->string('name');
            $table->string('slug', 64);
            $table->string('icon', 64)->nullable();
            $table->string('colour', 16)->default('gray');
            $table->foreignId('workflow_scheme_id')
                ->constrained(SAOTables::WorkflowSchemes->value, 'id', "{$table_name}_scheme_FK")
                ->restrictOnDelete();
            $table->boolean('is_defect')->default(false)->comment('Machine-readable hook: phase 2 creates tickets of this type from errors');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique('slug', "{$table_name}_slug_UN");
            $table->index('is_defect', "{$table_name}_is_defect_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::TicketTypes->value);
    }
};
