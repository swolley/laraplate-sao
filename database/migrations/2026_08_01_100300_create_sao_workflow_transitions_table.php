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
        $table_name = SAOTables::WorkflowTransitions->value;
        $statuses = SAOTables::TicketStatuses->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name, $statuses): void {
            $table->id();
            $table->foreignId('workflow_scheme_id')
                ->constrained(SAOTables::WorkflowSchemes->value, 'id', "{$table_name}_scheme_FK")
                ->cascadeOnDelete();
            $table->foreignId('from_status_id')
                ->nullable()
                ->comment('Null means the creation transition: it declares the scheme initial status')
                ->constrained($statuses, 'id', "{$table_name}_from_status_FK")
                ->restrictOnDelete();
            $table->foreignId('to_status_id')
                ->constrained($statuses, 'id', "{$table_name}_to_status_FK")
                ->restrictOnDelete();
            $table->string('label');
            $table->string('required_permission')->nullable();

            // Soft deletes are not optional: Core's base Model always applies the
            // trait, and its implementation scopes on an is_deleted column.
            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique(
                ['workflow_scheme_id', 'from_status_id', 'to_status_id'],
                "{$table_name}_move_UN",
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::WorkflowTransitions->value);
    }
};
