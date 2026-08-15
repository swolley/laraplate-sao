<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\SAO\Models\Ticket;

uses(RefreshDatabase::class);

test('watching a ticket is idempotent', function (): void {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create();

    $ticket->watch($user);
    $ticket->watch($user);

    expect($ticket->watchers()->get()->pluck('id')->all())->toBe([$user->id]);
});

test('unwatching removes the watcher', function (): void {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create();

    $ticket->watch($user);
    $ticket->unwatch($user);

    expect($ticket->fresh()->watchers()->get()->pluck('id')->all())->toBe([]);
});

test('the watchers list reflects exactly who watches', function (): void {
    $ticket = Ticket::factory()->create();
    $watching = User::factory()->create();
    $notWatching = User::factory()->create();

    $ticket->watch($watching);

    $ids = $ticket->watchers()->get()->pluck('id')->all();

    expect($ids)->toBe([$watching->id])
        ->and($ids)->not->toContain($notWatching->id);
});
