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
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Services\TicketBoardService;

uses(RefreshDatabase::class);

function sao_board_superadmin(): User
{
    $role = Role::query()->firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(SAOPermissionSeeder::class);
});

test('columns follow the status order and include empty statuses', function (): void {
    $this->actingAs(sao_board_superadmin());
    TicketStatus::query()->delete();
    $todo = TicketStatus::factory()->create(['name' => 'To do', 'order_column' => 1]);
    $doing = TicketStatus::factory()->create(['name' => 'Doing', 'order_column' => 2]);
    $done = TicketStatus::factory()->create(['name' => 'Done', 'order_column' => 3]);

    $project = Project::factory()->create();

    $columns = app(TicketBoardService::class)->for($project);

    expect($columns->map(fn ($c) => $c->status->id)->all())->toBe([$todo->id, $doing->id, $done->id])
        ->and($columns->every(fn ($c) => $c->tickets->isEmpty()))->toBeTrue();
});

test('tickets are grouped under their status', function (): void {
    $this->actingAs(sao_board_superadmin());
    $project = Project::factory()->create();
    $status = TicketStatus::factory()->create();

    $ticket = Ticket::factory()->forProject($project)->create();
    $ticket->forceFill(['ticket_status_id' => $status->id])->save();

    $columns = app(TicketBoardService::class)->for($project);
    $column = $columns->firstWhere(fn ($c) => $c->status->id === $status->id);

    expect($column->tickets->pluck('id')->all())->toContain($ticket->id);
});

test('only the chosen project tickets appear', function (): void {
    $this->actingAs(sao_board_superadmin());
    $mine = Project::factory()->create();
    $theirs = Project::factory()->create();

    $here = Ticket::factory()->forProject($mine)->create();
    Ticket::factory()->forProject($theirs)->create();

    $columns = app(TicketBoardService::class)->for($mine);
    $ids = $columns->flatMap(fn ($c) => $c->tickets->pluck('id'))->all();

    expect($ids)->toBe([$here->id]);
});

test('a ticket hidden by the ACL never appears on the board', function (): void {
    $mine = Project::factory()->create(['key_prefix' => 'MINE']);
    $visibleTicket = Ticket::factory()->forProject($mine)->create();

    $other = Project::factory()->create(['key_prefix' => 'THRS']);
    Ticket::factory()->forProject($other)->create();

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

    $mineColumns = app(TicketBoardService::class)->for($mine);
    $otherColumns = app(TicketBoardService::class)->for($other);

    expect($mineColumns->flatMap(fn ($c) => $c->tickets->pluck('id'))->all())->toBe([$visibleTicket->id])
        ->and($otherColumns->flatMap(fn ($c) => $c->tickets->pluck('id'))->all())->toBe([]);
});
