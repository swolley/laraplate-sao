<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\ReleaseStatus;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::Releases->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('project_id')
                ->constrained(SAOTables::Projects->value, 'id', "{$table_name}_project_FK")
                ->cascadeOnDelete();
            $table->string('version')->comment('The stable label the release is named as, e.g. 1.4.0');
            $table->enum('status', ReleaseStatus::values())->default(ReleaseStatus::Announced->value);
            $table->timestamp('released_at')->nullable()->comment('When a stable tag first realized the release');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique(['project_id', 'version'], "{$table_name}_project_version_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::Releases->value);
    }
};
