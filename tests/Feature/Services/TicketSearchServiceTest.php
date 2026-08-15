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
use Modules\SAO\Enums\TicketPriority;
use Modules\SAO\Models\Label;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Services\TicketSearchCriteria;
use Modules\SAO\Services\TicketSearchService;

uses(RefreshDatabase::class);

function sao_search_superadmin(): User
{
    $role = Role::query()->firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function sao_search(TicketSearchCriteria $criteria): array
{
    return app(TicketSearchService::class)->search($criteria)->pluck('id')->sort()->values()->all();
}

beforeEach(function (): void {
    $this->seed(SAOPermissionSeeder::class);
    $this->actingAs(sao_search_superadmin());
});

test('it filters by free text over title and description', function (): void {
    $project = Project::factory()->create();
    $hit = Ticket::factory()->forProject($project)->create(['title' => 'Database timeout on login']);
    $bodyHit = Ticket::factory()->forProject($project)->create(['title' => 'Other', 'description' => 'timeout happens nightly']);
    Ticket::factory()->forProject($project)->create(['title' => 'Unrelated', 'description' => 'nothing here']);

    expect(sao_search(new TicketSearchCriteria(text: 'timeout')))
        ->toBe(collect([$hit->id, $bodyHit->id])->sort()->values()->all());
});

test('it filters by status, type, priority and assignee', function (): void {
    $project = Project::factory()->create();
    $status = TicketStatus::factory()->create();
    $type = TicketType::factory()->create();
    $assignee = User::factory()->create();

    $match = Ticket::factory()->forProject($project)->create([
        'ticket_type_id' => $type->id,
        'priority' => TicketPriority::Urgent,
        'assignee_id' => $assignee->id,
    ]);
    $match->forceFill(['ticket_status_id' => $status->id])->save();

    Ticket::factory()->forProject($project)->create(['priority' => TicketPriority::Low]);

    expect(sao_search(new TicketSearchCriteria(statusId: $status->id)))->toBe([$match->id])
        ->and(sao_search(new TicketSearchCriteria(typeId: $type->id)))->toBe([$match->id])
        ->and(sao_search(new TicketSearchCriteria(priority: TicketPriority::Urgent)))->toBe([$match->id])
        ->and(sao_search(new TicketSearchCriteria(assigneeId: $assignee->id)))->toBe([$match->id]);
});

test('it filters by label', function (): void {
    $project = Project::factory()->create();
    $label = Label::factory()->for($project)->create();
    $tagged = Ticket::factory()->forProject($project)->create();
    Ticket::factory()->forProject($project)->create();

    $tagged->labels()->attach($label);

    expect(sao_search(new TicketSearchCriteria(labelId: $label->id)))->toBe([$tagged->id]);
});

test('it filters by due window and overdue flag', function (): void {
    $project = Project::factory()->create();
    $overdue = Ticket::factory()->forProject($project)->create(['due_at' => now()->subDay()]);
    $soon = Ticket::factory()->forProject($project)->create(['due_at' => now()->addDays(2)]);
    Ticket::factory()->forProject($project)->create(['due_at' => now()->addDays(40)]);

    expect(sao_search(new TicketSearchCriteria(dueBefore: now()->addDays(5))))
        ->toBe(collect([$overdue->id, $soon->id])->sort()->values()->all())
        ->and(sao_search(new TicketSearchCriteria(dueAfter: now()->addDays(30))))->toHaveCount(1)
        ->and(sao_search(new TicketSearchCriteria(isOverdue: true)))->toBe([$overdue->id]);
});

test('it never surfaces a ticket hidden by the ACL even when it matches', function (): void {
    $mine = Project::factory()->create(['key_prefix' => 'MINE']);
    $theirs = Project::factory()->create(['key_prefix' => 'THRS']);

    $visible = Ticket::factory()->forProject($mine)->create(['title' => 'shared subject']);
    Ticket::factory()->forProject($theirs)->create(['title' => 'shared subject']);

    $permission = Permission::query()
        ->where('name', PermissionName::forClass(Ticket::class, 'view'))
        ->firstOrFail();

    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->getKey(),
        'filters' => new FiltersGroup([
            new Filter('project_id', $mine->getKey(), FilterOperator::Equals),
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
    $this->actingAs($user);

    expect(sao_search(new TicketSearchCriteria(text: 'shared subject')))->toBe([$visible->id]);
});
