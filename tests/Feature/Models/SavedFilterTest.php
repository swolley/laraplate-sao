<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\SAO\Database\Seeders\SAOPermissionSeeder;
use Modules\SAO\Enums\TicketPriority;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\SavedFilter;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\TicketSearchCriteria;
use Modules\SAO\Services\TicketSearchService;

uses(RefreshDatabase::class);

test('a saved filter round-trips its criteria', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $criteria = new TicketSearchCriteria(
        text: 'timeout',
        priority: TicketPriority::High,
        isOverdue: true,
    );

    $filter = SavedFilter::query()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'name' => 'My urgent overdue',
        'criteria' => $criteria->toArray(),
    ]);

    $restored = $filter->fresh()->toCriteria();

    expect($restored->text)->toBe('timeout')
        ->and($restored->priority)->toBe(TicketPriority::High)
        ->and($restored->isOverdue)->toBeTrue();
});

test('reapplying a saved filter reproduces the same result set', function (): void {
    $this->seed(SAOPermissionSeeder::class);
    $role = Role::query()->firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user);

    $project = Project::factory()->create();
    $urgent = Ticket::factory()->forProject($project)->create(['priority' => TicketPriority::Urgent]);
    Ticket::factory()->forProject($project)->create(['priority' => TicketPriority::Low]);

    $criteria = new TicketSearchCriteria(priority: TicketPriority::Urgent);
    $direct = app(TicketSearchService::class)->search($criteria)->pluck('id')->all();

    $filter = SavedFilter::query()->create([
        'user_id' => $user->id,
        'name' => 'Urgent only',
        'criteria' => $criteria->toArray(),
    ]);

    $reapplied = app(TicketSearchService::class)->search($filter->fresh()->toCriteria())->pluck('id')->all();

    expect($reapplied)->toBe($direct)
        ->and($reapplied)->toBe([$urgent->id]);
});
