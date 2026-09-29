<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Casts\ActionEnum;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Media;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Search\OwnerAuthorizerRegistry;
use Modules\Core\Support\PermissionName;
use Modules\SAO\Database\Seeders\SAOPermissionSeeder;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\TicketOwnerAuthorizer;

uses(RefreshDatabase::class);

function ticketOwnerAuthorizerRestrictTo(Project $project): User
{
    $permission = Permission::findOrCreate(PermissionName::forClass(Ticket::class, ActionEnum::Select->value), 'web');

    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->getKey(),
        'filters' => new FiltersGroup([new Filter('project_id', $project->getKey(), FilterOperator::Equals)]),
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $role = Role::query()->create(['name' => 'sao-media-limited', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function ticketOwnedMedia(Ticket $ticket): Media
{
    $media = new Media();
    $media->forceFill([
        'collection_name' => 'default',
        'name' => 'log',
        'file_name' => 'log.txt',
        'mime_type' => 'text/plain',
        'disk' => 'public',
        'size' => 12,
        'model_type' => $ticket->getMorphClass(),
        'model_id' => $ticket->getKey(),
        'custom_properties' => [],
        'manipulations' => [],
        'generated_conversions' => [],
        'responsive_images' => [],
    ]);
    $media->saveQuietly();

    return $media;
}

it('registers the ticket owner authorizer', function (): void {
    expect(app(OwnerAuthorizerRegistry::class)->for(Ticket::class))->toBeInstanceOf(TicketOwnerAuthorizer::class);
});

it('keeps only the media of tickets the user can see', function (): void {
    config()->set('core.media.search_visibility', 'owner');
    $this->seed(SAOPermissionSeeder::class);

    $mine = Project::factory()->create(['key_prefix' => 'MINE']);
    $theirs = Project::factory()->create(['key_prefix' => 'THRS']);
    $kept = ticketOwnedMedia(Ticket::factory()->forProject($mine)->create());
    $dropped = ticketOwnedMedia(Ticket::factory()->forProject($theirs)->create());

    $this->actingAs(ticketOwnerAuthorizerRestrictTo($mine));

    $ids = (new Media())->authorizeSearchRehydration(Media::query()->whereKey([$kept->id, $dropped->id]))->pluck('id')->all();

    expect($ids)->toBe([$kept->id]);
});
