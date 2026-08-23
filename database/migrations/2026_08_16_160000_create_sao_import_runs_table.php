<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\ImportRunStatus;
use Modules\SAO\Enums\ImportScope;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::ImportRuns->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('binding_id')
                ->constrained(SAOTables::ProjectBindings->value, 'id', "{$table_name}_binding_FK")
                ->cascadeOnDelete();
            $table->enum('scope', ImportScope::values());
            $table->enum('status', ImportRunStatus::values())->default(ImportRunStatus::Running->value);
            $table->string('cursor')->nullable()->comment('The driver next-page cursor to resume from; null means start/exhausted');
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('filtered_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0)->comment('Comments imported for this run history');
            $table->unsignedInteger('attachment_count')->default(0)->comment('Attachments imported for this run history');
            $table->unsignedInteger('pages')->default(0)->comment('Total pages walked across every resume of this run');
            $table->boolean('truncated')->default(false)->comment('The last invocation hit the per-run page cap and stopped short');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->index(['binding_id', 'scope', 'status'], "{$table_name}_binding_scope_status_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::ImportRuns->value);
    }
};
