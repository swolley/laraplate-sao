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
        $table_name = SAOTables::SignalOccurrences->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('signal_id')
                ->constrained(SAOTables::Signals->value, 'id', "{$table_name}_signal_FK")
                ->cascadeOnDelete();
            $table->string('environment')->nullable()->comment('The deployed environment the occurrence came from');
            $table->json('context')->nullable()->comment('Optional tenant/user/request context from the payload');
            $table->timestamp('occurred_at')->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->index(['signal_id', 'occurred_at'], "{$table_name}_signal_time_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::SignalOccurrences->value);
    }
};
