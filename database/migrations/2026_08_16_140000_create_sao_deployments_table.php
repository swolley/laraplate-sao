<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\DeploymentStatus;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::Deployments->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('project_id')
                ->constrained(SAOTables::Projects->value, 'id', "{$table_name}_project_FK")
                ->cascadeOnDelete();
            $table->foreignId('environment_id')
                ->nullable()
                ->constrained(SAOTables::Environments->value, 'id', "{$table_name}_environment_FK")
                ->nullOnDelete();
            $table->foreignId('release_id')
                ->nullable()
                ->constrained(SAOTables::Releases->value, 'id', "{$table_name}_release_FK")
                ->nullOnDelete();
            $table->foreignId('connection_id')
                ->nullable()
                ->constrained(SAOTables::Connections->value, 'id', "{$table_name}_connection_FK")
                ->nullOnDelete();
            $table->string('version')->comment('The deployed version as reported, authoritative even without a release_id');
            $table->enum('status', DeploymentStatus::values())->default(DeploymentStatus::Started->value);
            $table->string('external_id')->nullable()->comment('Source id / delivery id — idempotency key together with connection_id');
            $table->timestamp('started_at')->comment('When the deploy/rollout began');
            $table->timestamp('finished_at')->nullable()->comment('Terminal timestamp — the window anchor for release health');
            $table->json('meta')->nullable()->comment('Driver-specific extras: canary weight, sha, actor');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique(['connection_id', 'external_id'], "{$table_name}_connection_external_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::Deployments->value);
    }
};
