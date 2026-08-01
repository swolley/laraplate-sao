<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Enums\StatusCategory;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::TicketStatuses->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->string('name');
            $table->enum('category', StatusCategory::values())->comment('Canonical meaning; phase 3 maps onto this, not onto the name');
            $table->string('colour', 16)->default('gray');
            $table->integer('order_column')->nullable(false)->default(0)->index("{$table_name}_order_column_IDX")->comment('The display order of the status');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique('name', "{$table_name}_name_UN");
            $table->index('category', "{$table_name}_category_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::TicketStatuses->value);
    }
};
