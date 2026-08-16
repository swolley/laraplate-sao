<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\ChangeRefType;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::ChangeRefs->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('ticket_id')
                ->constrained(SAOTables::Tickets->value, 'id', "{$table_name}_ticket_FK")
                ->cascadeOnDelete();
            $table->enum('type', ChangeRefType::values());
            $table->string('identifier')->comment('Commit sha, PR number/key, or tag name');
            $table->string('url')->nullable();
            $table->string('source')->nullable()->comment('The connection/driver key that produced the reference');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique(['ticket_id', 'type', 'identifier'], "{$table_name}_triple_UN");
            $table->index('identifier', "{$table_name}_identifier_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::ChangeRefs->value);
    }
};
