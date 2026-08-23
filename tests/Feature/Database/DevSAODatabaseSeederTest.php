<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Role;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Support\PermissionName;
use Modules\SAO\Database\Seeders\DevSAODatabaseSeeder;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\OwnershipSuggestion;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\WorkflowTransition;

uses(RefreshDatabase::class);

test('dev seeder creates the demo project, workflow and tickets across statuses', function (): void {
    $this->seed(DevSAODatabaseSeeder::class);

    expect(Project::query()->withoutGlobalScopes()->where('name', 'SAO Demo')->exists())->toBeTrue();
    expect(Ticket::query()->withoutGlobalScopes()->count())->toBe(8);
    expect(WorkflowTransition::query()->withoutGlobalScopes()->count())->toBe(6);

    $statusIds = TicketStatus::query()->withoutGlobalScopes()
        ->whereIn('name', ['To Do', 'In Progress', 'Blocked', 'Done'])
        ->pluck('id', 'name');
    expect($statusIds)->toHaveCount(4);

    foreach (['To Do', 'In Progress', 'Blocked', 'Done'] as $status) {
        expect(Ticket::query()->withoutGlobalScopes()->where('ticket_status_id', $statusIds[$status])->exists())
            ->toBeTrue("expected at least one ticket in status {$status}");
    }
});

test('dev seeder seeds operational data and is idempotent', function (): void {
    $this->seed(DevSAODatabaseSeeder::class);
    $this->seed(DevSAODatabaseSeeder::class);

    expect(Project::query()->withoutGlobalScopes()->where('name', 'SAO Demo')->count())->toBe(1);
    expect(Ticket::query()->withoutGlobalScopes()->count())->toBe(8);
    expect(Connection::query()->withoutGlobalScopes()->count())->toBe(2);
    expect(OwnershipSuggestion::query()->withoutGlobalScopes()->count())->toBe(3);
    expect(User::query()->where('email', 'sao.agent@laraplate.test')->count())->toBe(1);
});

test('seeded agent passes the sao scope and holds the ticket abilities', function (): void {
    $this->seed(DevSAODatabaseSeeder::class);

    $auth = app(AuthorizationService::class);
    $agent = User::query()->where('email', 'sao.agent@laraplate.test')->firstOrFail();

    expect($agent->hasRole('sao-agent'))->toBeTrue();
    expect($auth->userHasModuleAccess($agent, 'sao'))->toBeTrue();

    $perms = Role::findByName('sao-agent', 'web')->permissions->pluck('name');
    expect($perms)->toContain(PermissionName::forClass(Ticket::class, 'transition'));
    expect($perms)->toContain(PermissionName::forClass(Ticket::class, 'select'));
});
