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
        $table_name = SAOTables::Projects->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->string('name');
            $table->string('key_prefix', 10)->comment('Immutable once the first ticket number has been allocated');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('next_ticket_number')->default(0)->comment('Allocated under a row lock; gaps are accepted');
            $table->boolean('is_active')->default(true);

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique('key_prefix', "{$table_name}_key_prefix_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::Projects->value);
    }
};
