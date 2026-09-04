<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\SAO\Models\Ticket;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

/**
 * Optimistic locking end to end, on one of the two models that carry it.
 *
 * Two things were wrong before this work. The version the client holds was read straight off the
 * global request inside a model event, so it was stamped onto every row a write touched rather than
 * the one it belongs to, and it was picked up on queues and in the console where no such request
 * exists. And when the version did not match, the failure reached the caller as a reported 500:
 * ordinary concurrent editing looked like a server fault.
 */
function ticketLockingUrl(): string
{
    return route('core.crud.replace', ['module' => 'sao', 'entity' => 'tickets']);
}

function ticketLockingActor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    return $user;
}

it('answers 409 when the version the client holds is no longer the current one', function (): void {
    $this->actingAs(ticketLockingActor());

    $ticket = Ticket::factory()->create(['title' => 'as read by the client']);
    $stale_version = (int) $ticket->lock_version;

    // Somebody saves the record after this client has read it.
    $ticket->title = 'changed by somebody else';
    $ticket->save();

    $response = $this->patchJson(ticketLockingUrl(), [
        'id' => $ticket->id,
        'title' => 'written over a stale read',
        'lock_version' => $stale_version,
    ]);

    $response->assertStatus(Response::HTTP_CONFLICT);

    expect($ticket->fresh()?->title)->toBe('changed by somebody else');
});

it('accepts the update when the client holds the current version', function (): void {
    $this->actingAs(ticketLockingActor());

    $ticket = Ticket::factory()->create(['title' => 'as read by the client']);

    $response = $this->patchJson(ticketLockingUrl(), [
        'id' => $ticket->id,
        'title' => 'written over a fresh read',
        'lock_version' => (int) $ticket->fresh()?->lock_version,
    ]);

    $response->assertOk();

    expect($ticket->fresh()?->title)->toBe('written over a fresh read');
});

