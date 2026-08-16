<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\ReleaseTagKind;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::ReleaseTags->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('release_id')
                ->constrained(SAOTables::Releases->value, 'id', "{$table_name}_release_FK")
                ->cascadeOnDelete();
            $table->string('tag')->comment('The concrete VCS tag, e.g. v1.4.0 or v1.4.0-rc.1');
            $table->enum('kind', ReleaseTagKind::values());

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique(['release_id', 'tag'], "{$table_name}_release_tag_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::ReleaseTags->value);
    }
};
