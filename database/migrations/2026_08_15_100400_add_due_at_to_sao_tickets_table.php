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
        $table_name = SAOTables::Tickets->value;

        Schema::table($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->timestamp('due_at')->nullable()->after('assignee_id')->comment('When the ticket is due');
            $table->index('due_at', "{$table_name}_due_at_IDX");
        });
    }

    public function down(): void
    {
        Schema::table(SAOTables::Tickets->value, static function (Blueprint $table): void {
            $table->dropIndex(SAOTables::Tickets->value . '_due_at_IDX');
            $table->dropColumn('due_at');
        });
    }
};
