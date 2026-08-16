<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::Signals->value;

        Schema::table($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->foreignId('ticket_id')
                ->nullable()
                ->after('project_id')
                ->constrained(SAOTables::Tickets->value, 'id', "{$table_name}_ticket_FK")
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $table_name = SAOTables::Signals->value;

        Schema::table($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->dropForeign("{$table_name}_ticket_FK");
            $table->dropColumn('ticket_id');
        });
    }
};
