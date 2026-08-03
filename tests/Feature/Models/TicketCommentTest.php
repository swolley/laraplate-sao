<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Enums\CommentOrigin;
use Modules\SAO\Exceptions\ImmutableSystemCommentException;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketComment;

uses(RefreshDatabase::class);

test('a comment posted by a user is human and carries its author', function (): void {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create();

    $comment = TicketComment::postFor($ticket, 'Looking into it.', ChangeContext::forUser($user));

    expect($comment->origin)->toBe(CommentOrigin::Human);
    expect($comment->author_id)->toBe($user->id);
    expect($comment->source_key)->toBeNull();
});

test('a comment posted by automation is system and names its source', function (): void {
    $ticket = Ticket::factory()->create();

    $comment = TicketComment::postFor($ticket, 'Recurred 12 times.', ChangeContext::forAutomation('ingest'));

    expect($comment->origin)->toBe(CommentOrigin::System);
    expect($comment->author_id)->toBeNull();
    expect($comment->source_key)->toBe('ingest');
});

/**
 * A system comment records what automation observed. Letting a person rewrite it
 * would make the history untrustworthy exactly where it is most relied upon.
 */
test('a system comment cannot be edited', function (): void {
    $ticket = Ticket::factory()->create();
    $comment = TicketComment::postFor($ticket, 'Automated note.', ChangeContext::forAutomation('ingest'));

    $comment->body = 'Tampered.';

    expect(fn (): bool => $comment->save())
        ->toThrow(ImmutableSystemCommentException::class);

    expect($comment->fresh()->body)->toBe('Automated note.');
});

test('a human comment can be edited', function (): void {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create();
    $comment = TicketComment::postFor($ticket, 'Typo here.', ChangeContext::forUser($user));

    $comment->body = 'Fixed.';
    $comment->save();

    expect($comment->fresh()->body)->toBe('Fixed.');
});

test('comments belong to their ticket in the order they were written', function (): void {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create();

    TicketComment::postFor($ticket, 'One.', ChangeContext::forUser($user));
    TicketComment::postFor($ticket, 'Two.', ChangeContext::forAutomation('ingest'));

    expect($ticket->comments()->count())->toBe(2);
    expect($ticket->comments()->orderBy('id')->pluck('body')->all())->toBe(['One.', 'Two.']);
});

test('a new comment defaults to human origin', function (): void {
    expect((new TicketComment)->origin)->toBe(CommentOrigin::Human);
});
