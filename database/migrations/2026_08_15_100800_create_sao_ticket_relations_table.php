<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Enums\TicketRelationType;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::TicketRelations->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('source_ticket_id')
                ->constrained(SAOTables::Tickets->value, 'id', "{$table_name}_source_FK")
                ->cascadeOnDelete();
            $table->foreignId('target_ticket_id')
                ->constrained(SAOTables::Tickets->value, 'id', "{$table_name}_target_FK")
                ->cascadeOnDelete();
            $table->enum('type', TicketRelationType::values());

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique(['source_ticket_id', 'target_ticket_id', 'type'], "{$table_name}_triple_UN");
            $table->index('target_ticket_id', "{$table_name}_target_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::TicketRelations->value);
    }
};
