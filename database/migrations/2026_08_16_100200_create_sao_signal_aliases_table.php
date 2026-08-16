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
        $table_name = SAOTables::SignalAliases->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('signal_id')
                ->constrained(SAOTables::Signals->value, 'id', "{$table_name}_signal_FK")
                ->cascadeOnDelete();
            $table->string('group_key')->comment('A superseded group key that now points at this signal');
            $table->unsignedSmallInteger('algo_version')->default(1);

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique('group_key', "{$table_name}_key_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::SignalAliases->value);
    }
};
