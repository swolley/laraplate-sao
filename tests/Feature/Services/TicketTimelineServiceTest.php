<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketComment;
use Modules\SAO\Services\TicketTimelineService;

uses(RefreshDatabase::class);

/**
 * Opening a ticket is history — the first line any tracker shows — so it earns
 * its own kind rather than being hidden or mislabelled as a field change.
 */
test('an untouched ticket shows only its opening', function (): void {
    $ticket = Ticket::factory()->create();

    $timeline = app(TicketTimelineService::class)->for($ticket);

    expect($timeline)->toHaveCount(1);
    expect($timeline->first()->kind())->toBe('created');
});

test('comments appear as timeline entries in chronological order', function (): void {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create();

    TicketComment::postFor($ticket, 'First.', ChangeContext::forUser($user));
    TicketComment::postFor($ticket, 'Second.', ChangeContext::forAutomation('ingest'));

    $comments = app(TicketTimelineService::class)->for($ticket)
        ->filter(fn ($entry): bool => $entry->kind() === 'comment')
        ->values();

    expect($comments)->toHaveCount(2);
    expect($comments->first()->body())->toBe('First.');
    expect($comments->first()->authorId())->toBe($user->id);
    expect($comments->last()->sourceKey())->toBe('ingest');
});

test('a field change appears as a change entry naming what changed', function (): void {
    $ticket = Ticket::factory()->create(['title' => 'Before']);

    $ticket->title = 'After';
    $ticket->save();

    $changes = app(TicketTimelineService::class)->for($ticket)
        ->filter(fn ($entry): bool => $entry->kind() === 'change');

    expect($changes)->not->toBeEmpty();
    expect(array_keys($changes->last()->changes()))->toContain('title');
});

/**
 * Core stores the attributes before as well as after, so the timeline can say
 * "from Before to After" instead of only naming the field that moved.
 */
test('a change entry carries the previous value as well as the new one', function (): void {
    $ticket = Ticket::factory()->create(['title' => 'Before']);

    $ticket->title = 'After';
    $ticket->save();

    $change = app(TicketTimelineService::class)->for($ticket)
        ->filter(fn ($entry): bool => $entry->kind() === 'change')
        ->last();

    expect($change->changes()['title'])->toBe('After');
    expect($change->previous()['title'])->toBe('Before');
});

test('bookkeeping columns stay out of the history', function (): void {
    $ticket = Ticket::factory()->create(['title' => 'Before']);

    $ticket->title = 'After';
    $ticket->save();

    $change = app(TicketTimelineService::class)->for($ticket)
        ->filter(fn ($entry): bool => $entry->kind() === 'change')
        ->last();

    expect(array_keys($change->changes()))->not->toContain('updated_at');
    expect(array_keys($change->changes()))->not->toContain('lock_version');
});

test('comments and changes are merged into one ordered stream', function (): void {
    $ticket = Ticket::factory()->create(['title' => 'Before']);
    $user = User::factory()->create();

    TicketComment::postFor($ticket, 'A note.', ChangeContext::forUser($user));
    $ticket->title = 'After';
    $ticket->save();

    $timeline = app(TicketTimelineService::class)->for($ticket);
    $kinds = $timeline->map(fn ($entry): string => $entry->kind())->all();

    expect($kinds)->toContain('comment');
    expect($kinds)->toContain('change');

    $timestamps = $timeline->map(fn ($entry): int => $entry->occurredAt()->getTimestamp())->all();
    $sorted = $timestamps;
    sort($sorted);

    expect($timestamps)->toBe($sorted);
});
