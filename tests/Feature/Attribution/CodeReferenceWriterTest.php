<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Attribution\CodeReference;
use Modules\SAO\Attribution\CodeReferenceWriter;
use Modules\SAO\Enums\ChangeRefRelation;
use Modules\SAO\Enums\ChangeRefType;
use Modules\SAO\Models\ChangeRef;
use Modules\SAO\Models\Ticket;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->writer = app(CodeReferenceWriter::class);
});

test('a fixing commit creates a fix change ref on the referenced ticket', function (): void {
    $ticket = Ticket::factory()->create();

    $outcome = $this->writer->write(new CodeReference(
        type: ChangeRefType::Commit,
        identifier: 'abc123',
        text: "Fixes {$ticket->key}: null guard",
        source: 'github',
    ));

    expect($outcome->changeRefs)->toHaveCount(1)
        ->and($outcome->unknownKeys)->toBe([]);

    $changeRef = ChangeRef::query()->where('ticket_id', $ticket->id)->sole();

    expect($changeRef->type)->toBe(ChangeRefType::Commit)
        ->and($changeRef->relation)->toBe(ChangeRefRelation::Fixes)
        ->and($changeRef->identifier)->toBe('abc123');
});

test('a bare mention creates a mention change ref', function (): void {
    $ticket = Ticket::factory()->create();

    $this->writer->write(new CodeReference(
        type: ChangeRefType::Commit,
        identifier: 'def456',
        text: "refactor, see {$ticket->key}",
    ));

    $changeRef = ChangeRef::query()->where('ticket_id', $ticket->id)->sole();

    expect($changeRef->relation)->toBe(ChangeRefRelation::Mentions);
});

test('re-processing the same artefact is idempotent', function (): void {
    $ticket = Ticket::factory()->create();
    $reference = new CodeReference(ChangeRefType::PullRequest, '7', "Closes {$ticket->key}", mergedAt: now());

    $this->writer->write($reference);
    $this->writer->write($reference);

    expect(ChangeRef::query()->where('ticket_id', $ticket->id)->count())->toBe(1);
});

test('a later fix upgrades an earlier mention but never downgrades', function (): void {
    $ticket = Ticket::factory()->create();

    $this->writer->write(new CodeReference(ChangeRefType::Commit, 'sha1', "touches {$ticket->key}"));

    expect(ChangeRef::query()->where('ticket_id', $ticket->id)->sole()->relation)
        ->toBe(ChangeRefRelation::Mentions);

    // Same commit, now with a closing verb → upgrade to fix.
    $this->writer->write(new CodeReference(ChangeRefType::Commit, 'sha1', "fixes {$ticket->key}"));

    expect(ChangeRef::query()->where('ticket_id', $ticket->id)->sole()->relation)
        ->toBe(ChangeRefRelation::Fixes);

    // A subsequent mention must not downgrade the recorded fix.
    $this->writer->write(new CodeReference(ChangeRefType::Commit, 'sha1', "see {$ticket->key}"));

    expect(ChangeRef::query()->where('ticket_id', $ticket->id)->sole()->relation)
        ->toBe(ChangeRefRelation::Fixes);
});

test('an unknown ticket key is reported and creates nothing', function (): void {
    $outcome = $this->writer->write(new CodeReference(
        type: ChangeRefType::Commit,
        identifier: 'ghost',
        text: 'fixes ZZZ-999',
    ));

    expect($outcome->changeRefs)->toBe([])
        ->and($outcome->unknownKeys)->toBe(['ZZZ-999'])
        ->and(ChangeRef::query()->count())->toBe(0);
});

test('one commit fixing several tickets links each of them', function (): void {
    $first = Ticket::factory()->create();
    $second = Ticket::factory()->create();

    $outcome = $this->writer->write(new CodeReference(
        type: ChangeRefType::Commit,
        identifier: 'multi',
        text: "fixes {$first->key}, closes {$second->key}",
    ));

    expect($outcome->changeRefs)->toHaveCount(2)
        ->and(ChangeRef::query()->where('ticket_id', $first->id)->sole()->relation)->toBe(ChangeRefRelation::Fixes)
        ->and(ChangeRef::query()->where('ticket_id', $second->id)->sole()->relation)->toBe(ChangeRefRelation::Fixes);
});
