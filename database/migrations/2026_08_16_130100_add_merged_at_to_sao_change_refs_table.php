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
        Schema::table(SAOTables::ChangeRefs->value, static function (Blueprint $table): void {
            $table->timestamp('merged_at')
                ->nullable()
                ->after('source')
                ->comment('When a pull_request reference was merged; null otherwise');
        });
    }

    public function down(): void
    {
        Schema::table(SAOTables::ChangeRefs->value, static function (Blueprint $table): void {
            $table->dropColumn('merged_at');
        });
    }
};
