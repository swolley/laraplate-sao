<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Modules\SAO\Database\Seeders\SAOPermissionSeeder;
use Modules\SAO\Drivers\Internal\InternalIssuesDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;
use Modules\SAO\Models\WorkflowTransition;
use Modules\SAO\Tests\Support\Conformance\IssuesConformance;

uses(RefreshDatabase::class);

/**
 * @return array{0: Project, 1: TicketType}
 */
function internal_driver_fixture(): array
{
    $open = TicketStatus::factory()->category(StatusCategory::Open)->create(['name' => 'Open']);

    $scheme = WorkflowScheme::factory()->create(['name' => 'Simple']);
    WorkflowTransition::factory()->for($scheme, 'scheme')->create([
        'from_status_id' => null,
        'to_status_id' => $open->id,
        'label' => 'Open',
    ]);

    $type = TicketType::factory()->create(['workflow_scheme_id' => $scheme->id]);
    $project = Project::factory()->create(['key_prefix' => 'SAO']);
    $project->ticketTypes()->attach($type->id, ['is_default' => true]);

    return [$project, $type];
}

function internal_driver_login(): void
{
    $permission = Permission::query()
        ->where('name', PermissionName::forClass(Ticket::class, 'view'))
        ->firstOrFail();

    $user = User::factory()->create();
    $user->givePermissionTo($permission);

    Auth::login($user);
}

function internal_driver_context(Project $project, TicketType $type, int $pageSize = 2): BindingContext
{
    return new BindingContext(
        new ConnectionContext(baseUrl: null, credentials: []),
        config: ['project' => $project->id, 'ticket_type' => $type->id, 'page_size' => $pageSize],
    );
}

beforeEach(function (): void {
    $this->seed(SAOPermissionSeeder::class);
});

test('the internal issues driver passes the issues conformance battery', function (): void {
    [$project, $type] = internal_driver_fixture();
    internal_driver_login();

    // Three tickets with a page size of two forces the conformance's multi-page check.
    foreach (range(1, 3) as $ignored) {
        Ticket::factory()->forProject($project)->create();
    }

    IssuesConformance::assert(app(InternalIssuesDriver::class), internal_driver_context($project, $type));
});

test('writes create real, ACL-scoped tickets readable back through the driver', function (): void {
    [$project, $type] = internal_driver_fixture();
    internal_driver_login();

    $driver = app(InternalIssuesDriver::class);
    $context = internal_driver_context($project, $type);

    $created = $driver->create($context, ['title' => 'From driver', 'body' => 'hello']);

    expect($created['title'])->toBe('From driver');

    $found = $driver->lookup($context, (string) $created['remote_id']);

    expect($found)->not->toBeNull()
        ->and($found['title'])->toBe('From driver')
        ->and(Ticket::query()->where('key', $created['remote_id'])->exists())->toBeTrue();
});
