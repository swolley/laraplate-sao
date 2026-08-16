<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\OwnershipRule;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::OwnershipSuggestions->value;
        $users = CoreTables::Users->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name, $users): void {
            $table->id();
            $table->foreignId('ticket_id')
                ->constrained(SAOTables::Tickets->value, 'id', "{$table_name}_ticket_FK")
                ->cascadeOnDelete();
            $table->foreignId('suggested_user_id')
                ->nullable()
                ->constrained($users, 'id', "{$table_name}_user_FK")
                ->nullOnDelete();
            $table->enum('rule', OwnershipRule::values());
            $table->float('score')->default(0);
            $table->json('evidence')->comment('The paths/commits and rule-specific data behind the suggestion');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->index('ticket_id', "{$table_name}_ticket_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::OwnershipSuggestions->value);
    }
};
