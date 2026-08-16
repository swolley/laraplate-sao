<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\ChangeRefType;
use Modules\SAO\Models\ChangeRef;
use Modules\SAO\Models\Ticket;

uses(RefreshDatabase::class);

test('a ticket lists its code-to-work references by type', function (): void {
    $ticket = Ticket::factory()->create();
    ChangeRef::factory()->create(['ticket_id' => $ticket->id, 'type' => ChangeRefType::Commit, 'identifier' => 'abc']);
    ChangeRef::factory()->create(['ticket_id' => $ticket->id, 'type' => ChangeRefType::PullRequest, 'identifier' => '42']);

    expect($ticket->changeRefs()->count())->toBe(2)
        ->and($ticket->changeRefs()->where('type', ChangeRefType::PullRequest)->first()->identifier)->toBe('42');
});

test('the same artefact cannot be linked to a ticket twice', function (): void {
    $ticket = Ticket::factory()->create();
    ChangeRef::factory()->create(['ticket_id' => $ticket->id, 'type' => ChangeRefType::Tag, 'identifier' => 'v1.0.0']);

    expect(fn (): ChangeRef => ChangeRef::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ChangeRefType::Tag,
        'identifier' => 'v1.0.0',
    ]))->toThrow(QueryException::class);
});
