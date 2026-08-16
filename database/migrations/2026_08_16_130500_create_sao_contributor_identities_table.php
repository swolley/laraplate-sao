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
        $table_name = SAOTables::ContributorIdentities->value;
        $users = CoreTables::Users->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name, $users): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained($users, 'id', "{$table_name}_user_FK")
                ->cascadeOnDelete();
            $table->string('provider')->default('')->comment("Driver key (github/gitlab/…); '' means any provider");
            $table->string('identity')->comment('A VCS handle (octocat) or a git author email');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique(['provider', 'identity'], "{$table_name}_provider_identity_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::ContributorIdentities->value);
    }
};
