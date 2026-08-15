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
        $table_name = SAOTables::SyncOperations->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('binding_id')->constrained(SAOTables::ProjectBindings->value)->cascadeOnDelete();
            $table->string('idempotency_key')->comment('Stable key over binding, ticket and content so a retry is a no-op');
            $table->string('outcome');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique('idempotency_key', "{$table_name}_key_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::SyncOperations->value);
    }
};
