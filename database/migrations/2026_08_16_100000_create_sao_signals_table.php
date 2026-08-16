<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Enums\SignalState;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::Signals->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('project_id')
                ->constrained(SAOTables::Projects->value, 'id', "{$table_name}_project_FK")
                ->cascadeOnDelete();
            $table->string('group_key');
            // Stored from the first migration so the fingerprint algorithm can be
            // versioned later without backfilling unknown values (spec §7).
            $table->unsignedSmallInteger('algo_version')->default(1);
            $table->enum('state', SignalState::values())->default(SignalState::Open->value);
            $table->unsignedBigInteger('occurrence_count')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            // group_key is comparable across projects (D13) but unique within one:
            // the same bug in two projects is two signals, one per project.
            $table->unique(['project_id', 'group_key'], "{$table_name}_project_key_UN");
            $table->index('group_key', "{$table_name}_key_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::Signals->value);
    }
};
