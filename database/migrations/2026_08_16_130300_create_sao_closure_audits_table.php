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
        $table_name = SAOTables::ClosureAudits->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('ticket_id')
                ->constrained(SAOTables::Tickets->value, 'id', "{$table_name}_ticket_FK")
                ->cascadeOnDelete();
            $table->foreignId('closure_policy_id')
                ->nullable()
                ->constrained(SAOTables::ClosurePolicies->value, 'id', "{$table_name}_policy_FK")
                ->nullOnDelete();
            $table->enum('action', ClosureAction::values());
            $table->json('conditions_held')->comment('The per-condition outcomes and evidence — the "closed because"');
            $table->string('reporting_environment')->nullable();
            $table->timestamp('closed_at');
            $table->timestamp('reopened_at')->nullable();
            $table->unsignedBigInteger('returned_after_seconds')->nullable();
            $table->foreignId('returned_occurrence_id')
                ->nullable()
                ->constrained(SAOTables::SignalOccurrences->value, 'id', "{$table_name}_occurrence_FK")
                ->nullOnDelete();
            $table->boolean('is_premature')->default(false);

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->index('ticket_id', "{$table_name}_ticket_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::ClosureAudits->value);
    }
};
