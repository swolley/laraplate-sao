<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\SAO\Enums\ChangeRefRelation;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(SAOTables::ChangeRefs->value, static function (Blueprint $table): void {
            $table->enum('relation', ChangeRefRelation::values())
                ->default(ChangeRefRelation::Fixes->value)
                ->after('type')
                ->comment('Whether the change resolves (fixes) or only mentions the ticket');
        });
    }

    public function down(): void
    {
        Schema::table(SAOTables::ChangeRefs->value, static function (Blueprint $table): void {
            $table->dropColumn('relation');
        });
    }
};
