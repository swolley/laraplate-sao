<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Models\Label;
use Modules\SAO\Models\Pivot\TicketLabel;
use Modules\SAO\Models\Pivot\TicketWatcher;
use Modules\SAO\Models\Ticket;

uses(RefreshDatabase::class);

test('a ticket-label assignment is a TicketLabel pivot carrying timestamps', function (): void {
    $ticket = Ticket::factory()->create();
    $label = Label::factory()->create();

    $ticket->labels()->attach($label->id);

    $pivot = $ticket->labels()->first()->pivot;
    expect($pivot)->toBeInstanceOf(TicketLabel::class);
    expect($pivot->created_at)->not->toBeNull();
    expect($pivot->ticket)->toBeInstanceOf(Ticket::class);
    expect($pivot->label)->toBeInstanceOf(Label::class);
});

test('a ticket watcher is a TicketWatcher pivot carrying timestamps', function (): void {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create();

    $ticket->watchers()->attach($user->id);

    $pivot = $ticket->watchers()->first()->pivot;
    expect($pivot)->toBeInstanceOf(TicketWatcher::class);
    expect($pivot->created_at)->not->toBeNull();
    expect($pivot->ticket)->toBeInstanceOf(Ticket::class);
    expect($pivot->user?->getKey())->toBe($user->getKey());
});
