<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\TicketRelationType;
use Modules\SAO\Exceptions\SelfTicketRelationException;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketRelation;

uses(RefreshDatabase::class);

function makeTicketRelation(Ticket $source, Ticket $target, TicketRelationType $type): TicketRelation
{
    return TicketRelation::query()->create([
        'source_ticket_id' => $source->id,
        'target_ticket_id' => $target->id,
        'type' => $type,
    ]);
}

test('a ticket resolves the tickets it blocks, duplicates and relates to', function (): void {
    $ticket = Ticket::factory()->create();
    $blocked = Ticket::factory()->create();
    $duplicate = Ticket::factory()->create();
    $related = Ticket::factory()->create();

    makeTicketRelation($ticket, $blocked, TicketRelationType::Blocks);
    makeTicketRelation($ticket, $duplicate, TicketRelationType::Duplicates);
    makeTicketRelation($ticket, $related, TicketRelationType::Relates);

    expect($ticket->relatedVia(TicketRelationType::Blocks)->pluck('id')->all())->toBe([$blocked->id])
        ->and($ticket->relatedVia(TicketRelationType::Duplicates)->pluck('id')->all())->toBe([$duplicate->id])
        ->and($ticket->relatedVia(TicketRelationType::Relates)->pluck('id')->all())->toBe([$related->id]);
});

test('the inverse of a blocks relation resolves as blocked by', function (): void {
    $blocker = Ticket::factory()->create();
    $blocked = Ticket::factory()->create();

    makeTicketRelation($blocker, $blocked, TicketRelationType::Blocks);

    expect($blocked->inverselyRelatedVia(TicketRelationType::Blocks)->pluck('id')->all())->toBe([$blocker->id])
        ->and($blocker->inverselyRelatedVia(TicketRelationType::Blocks)->all())->toBe([]);
});

test('a symmetric relates relation resolves from both tickets', function (): void {
    $first = Ticket::factory()->create();
    $second = Ticket::factory()->create();

    makeTicketRelation($first, $second, TicketRelationType::Relates);

    expect($first->relatedVia(TicketRelationType::Relates)->pluck('id')->all())->toContain($second->id)
        ->and($second->relatedVia(TicketRelationType::Relates)->pluck('id')->all())->toContain($first->id);
});

test('a ticket cannot relate to itself', function (): void {
    $ticket = Ticket::factory()->create();

    expect(fn (): TicketRelation => makeTicketRelation($ticket, $ticket, TicketRelationType::Blocks))
        ->toThrow(SelfTicketRelationException::class);
});

test('the same relation triple cannot be created twice', function (): void {
    $source = Ticket::factory()->create();
    $target = Ticket::factory()->create();

    makeTicketRelation($source, $target, TicketRelationType::Blocks);

    expect(fn (): TicketRelation => makeTicketRelation($source, $target, TicketRelationType::Blocks))
        ->toThrow(QueryException::class);
});

test('blocks and duplicates are directional while relates is symmetric', function (): void {
    expect(TicketRelationType::Blocks->isDirectional())->toBeTrue()
        ->and(TicketRelationType::Duplicates->isDirectional())->toBeTrue()
        ->and(TicketRelationType::Relates->isDirectional())->toBeFalse();
});
