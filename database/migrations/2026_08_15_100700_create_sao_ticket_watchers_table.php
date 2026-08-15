<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::TicketWatchers->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('ticket_id')
                ->constrained(SAOTables::Tickets->value, 'id', "{$table_name}_ticket_FK")
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained(CoreTables::Users->value, 'id', "{$table_name}_user_FK")
                ->cascadeOnDelete();

            // Plain pivot: watch/unwatch is the whole lifecycle, no soft deletes.
            MigrateUtils::timestamps($table, hasCreateUpdate: true);

            $table->unique(['ticket_id', 'user_id'], "{$table_name}_pair_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::TicketWatchers->value);
    }
};
