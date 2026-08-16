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
            $table->string('base_ref')->nullable()->after('merged_at')
                ->comment('A pull request base branch/sha, for comparing its changed files');
            $table->string('head_ref')->nullable()->after('base_ref')
                ->comment('A pull request head branch/sha');
        });
    }

    public function down(): void
    {
        Schema::table(SAOTables::ChangeRefs->value, static function (Blueprint $table): void {
            $table->dropColumn(['base_ref', 'head_ref']);
        });
    }
};
