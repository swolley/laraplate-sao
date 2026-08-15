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
        $table_name = SAOTables::TicketLinks->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained(SAOTables::Tickets->value)->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained(SAOTables::Connections->value)->cascadeOnDelete();
            $table->string('remote_id')->comment('Identifier of the linked object in the external tracker');
            $table->string('url')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_state')->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique(['connection_id', 'remote_id'], "{$table_name}_remote_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::TicketLinks->value);
    }
};
