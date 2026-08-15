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
        Schema::table(SAOTables::Connections->value, static function (Blueprint $table): void {
            $table->json('config')->nullable()->after('credential_ref')->comment('Non-secret connection-level configuration');
        });
    }

    public function down(): void
    {
        Schema::table(SAOTables::Connections->value, static function (Blueprint $table): void {
            $table->dropColumn('config');
        });
    }
};
