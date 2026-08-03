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
        $table_name = SAOTables::ProjectTicketTypes->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('project_id')
                ->constrained(SAOTables::Projects->value, 'id', "{$table_name}_project_FK")
                ->cascadeOnDelete();
            $table->foreignId('ticket_type_id')
                ->constrained(SAOTables::TicketTypes->value, 'id', "{$table_name}_type_FK")
                ->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->foreignId('workflow_scheme_id')
                ->nullable()
                ->comment('Optional per-project override; null means the type own scheme applies')
                ->constrained(SAOTables::WorkflowSchemes->value, 'id', "{$table_name}_scheme_FK")
                ->restrictOnDelete();

            // No soft deletes: the pivot extends Core's Pivot, which carries only
            // HasFactory and HasPrefixedTableName, not the soft-delete scope.
            MigrateUtils::timestamps($table, hasCreateUpdate: true);

            $table->unique(['project_id', 'ticket_type_id'], "{$table_name}_pair_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::ProjectTicketTypes->value);
    }
};
