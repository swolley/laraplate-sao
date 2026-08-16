<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\ReleaseStatus;
use Modules\SAO\Enums\ReleaseTagKind;
use Modules\SAO\Enums\TicketReleaseState;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\ReleaseTag;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketRelease;

uses(RefreshDatabase::class);

test('a release carries both a stable and a candidate tag', function (): void {
    $release = Release::factory()->create();
    ReleaseTag::factory()->for($release)->create(['kind' => ReleaseTagKind::Stable]);
    ReleaseTag::factory()->for($release)->candidate()->create();

    expect($release->tags()->count())->toBe(2)
        ->and($release->tags()->where('kind', ReleaseTagKind::Stable)->exists())->toBeTrue()
        ->and($release->tags()->where('kind', ReleaseTagKind::Candidate)->exists())->toBeTrue();
});

test('the same tag cannot be recorded on a release twice', function (): void {
    $release = Release::factory()->create();
    ReleaseTag::factory()->for($release)->create(['tag' => 'v1.0.0', 'kind' => ReleaseTagKind::Stable]);

    expect(fn (): ReleaseTag => ReleaseTag::factory()->for($release)->create([
        'tag' => 'v1.0.0',
        'kind' => ReleaseTagKind::Candidate,
    ]))->toThrow(QueryException::class);
});

test('a ticket release pair is unique', function (): void {
    $ticket = Ticket::factory()->create();
    $release = Release::factory()->create();
    TicketRelease::factory()->create([
        'ticket_id' => $ticket->id,
        'release_id' => $release->id,
        'state' => TicketReleaseState::Promised,
    ]);

    expect(fn (): TicketRelease => TicketRelease::factory()->create([
        'ticket_id' => $ticket->id,
        'release_id' => $release->id,
        'state' => TicketReleaseState::Shipped,
    ]))->toThrow(QueryException::class);
});

test('a ticket can be shipped in a release regardless of its workflow status', function (): void {
    $ticket = Ticket::factory()->create();
    $release = Release::factory()->shipped()->create();
    TicketRelease::factory()->create([
        'ticket_id' => $ticket->id,
        'release_id' => $release->id,
        'state' => TicketReleaseState::Shipped,
    ]);

    $ticket->refresh();

    expect($ticket->releases()->count())->toBe(1)
        ->and($ticket->releases()->first()->pivot->state)->toBe(TicketReleaseState::Shipped->value)
        ->and($release->status)->toBe(ReleaseStatus::Shipped);
});
