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
        $table_name = SAOTables::SourceProfiles->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->json('matchers')->comment('Rules selecting which payloads this profile applies to');
            $table->json('field_bindings')->comment('Canonical field => payload dot-path');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique('name', "{$table_name}_name_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::SourceProfiles->value);
    }
};
