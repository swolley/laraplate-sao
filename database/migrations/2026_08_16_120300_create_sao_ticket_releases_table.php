<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Enums\TicketReleaseState;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::TicketReleases->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('ticket_id')
                ->constrained(SAOTables::Tickets->value, 'id', "{$table_name}_ticket_FK")
                ->cascadeOnDelete();
            $table->foreignId('release_id')
                ->constrained(SAOTables::Releases->value, 'id', "{$table_name}_release_FK")
                ->cascadeOnDelete();
            $table->enum('state', TicketReleaseState::values());

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique(['ticket_id', 'release_id'], "{$table_name}_ticket_release_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::TicketReleases->value);
    }
};
