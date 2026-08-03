<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Enums\TicketPriority;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::Tickets->value;
        $users = CoreTables::Users->value;
        $lock_version_column = config('core.locking.lock_version_column');

        Schema::create($table_name, static function (Blueprint $table) use ($table_name, $users, $lock_version_column): void {
            $table->id();
            $table->foreignId('project_id')
                ->constrained(SAOTables::Projects->value, 'id', "{$table_name}_project_FK")
                ->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('key', 32);
            $table->foreignId('ticket_type_id')
                ->constrained(SAOTables::TicketTypes->value, 'id', "{$table_name}_type_FK")
                ->restrictOnDelete();
            $table->foreignId('ticket_status_id')
                ->constrained(SAOTables::TicketStatuses->value, 'id', "{$table_name}_status_FK")
                ->restrictOnDelete();
            $table->enum('priority', TicketPriority::values())->default(TicketPriority::Normal->value);
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('reporter_id')->nullable()
                ->constrained($users, 'id', "{$table_name}_reporter_FK")
                ->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()
                ->constrained($users, 'id', "{$table_name}_assignee_FK")
                ->nullOnDelete();
            $table->integer($lock_version_column)->unsigned()->nullable(false)->default(1)->comment('The optimistic lock version of the ticket');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique('key', "{$table_name}_key_UN");
            $table->unique(['project_id', 'number'], "{$table_name}_project_number_UN");
            $table->index(['project_id', 'ticket_status_id'], "{$table_name}_project_status_IDX");
            $table->index('assignee_id', "{$table_name}_assignee_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::Tickets->value);
    }
};
