<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Modules\SAO\Database\Seeders\SAOPermissionSeeder;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\TicketQueryService;

uses(RefreshDatabase::class);

/**
 * Attaches an ACL restricting the ticket view permission to one project, gives
 * the current user a role holding it, and returns the user.
 */
function sao_restrict_tickets_to(Project $project): User
{
    $permission = Permission::query()
        ->where('name', PermissionName::forClass(Ticket::class, 'view'))
        ->firstOrFail();

    // Same recipe Core's own AclResolverServiceTest uses: FiltersGroupCast
    // serializes the value before the QueryBuilder rule sees it, so the rule
    // receives a string and always fails. Validation is skipped and the group
    // force-filled as an object.
    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->getKey(),
        'filters' => new FiltersGroup([
            new Filter('project_id', $project->getKey(), FilterOperator::Equals),
        ]),
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $role = Role::query()->create(['name' => 'sao-limited', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * This is the test the design asks for. Task 10 could only assert that a read
 * path service exists; this proves the filters actually hide rows.
 */
test('an ACL restricting the view permission hides other projects tickets', function (): void {
    $this->seed(SAOPermissionSeeder::class);

    $mine = Project::factory()->create(['key_prefix' => 'MINE']);
    $theirs = Project::factory()->create(['key_prefix' => 'THRS']);

    Ticket::factory()->forProject($mine)->create();
    Ticket::factory()->forProject($mine)->create();
    Ticket::factory()->forProject($theirs)->create();
    Ticket::factory()->forProject($theirs)->create();
    Ticket::factory()->forProject($theirs)->create();

    expect(Ticket::query()->count())->toBe(5);

    $this->actingAs(sao_restrict_tickets_to($mine));

    expect(app(TicketQueryService::class)->visible()->count())->toBe(2);
});

test('the restricted view returns only the permitted project tickets', function (): void {
    $this->seed(SAOPermissionSeeder::class);

    $mine = Project::factory()->create(['key_prefix' => 'MINE']);
    $theirs = Project::factory()->create(['key_prefix' => 'THRS']);

    Ticket::factory()->forProject($mine)->create();
    Ticket::factory()->forProject($theirs)->create();

    $this->actingAs(sao_restrict_tickets_to($mine));

    $visible = app(TicketQueryService::class)->visible()->get();

    expect($visible->pluck('project_id')->unique()->all())->toBe([$mine->id]);
});
