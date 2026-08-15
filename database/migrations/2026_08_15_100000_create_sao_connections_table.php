<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\ConnectionHealth;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::Connections->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->string('driver_key')->index("{$table_name}_driver_key_IDX")->comment('Registered driver this connection instantiates');
            $table->string('name');
            $table->string('base_url')->nullable()->comment('Non-secret endpoint coordinate');
            $table->text('credential')->nullable()->comment('Encrypted-at-rest secret payload (write-only); null when credential_ref is used');
            $table->string('credential_ref')->nullable()->comment('Env/config key that overrides the encrypted credential when set');
            $table->json('capabilities')->comment('Subset of the driver capabilities this connection exposes');
            $table->enum('health_state', ConnectionHealth::values())->default(ConnectionHealth::Unknown->value)->comment('Last known reachability');
            $table->timestamp('last_checked_at')->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique('name', "{$table_name}_name_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::Connections->value);
    }
};
