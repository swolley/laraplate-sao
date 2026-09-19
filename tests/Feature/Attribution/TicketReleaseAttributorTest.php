<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Attribution\TicketReleaseAttributor;
use Modules\SAO\Enums\TicketReleaseState;
use Modules\SAO\Exceptions\UnstableResolutionReleaseException;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\ReleaseTag;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketRelease;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->attributor = app(TicketReleaseAttributor::class);
});

test('shipping a ticket to a release without a stable tag is rejected', function (): void {
    $ticket = Ticket::factory()->create();
    $release = Release::factory()->create();
    $release->tags()->save(ReleaseTag::factory()->candidate()->make());

    expect(fn (): TicketRelease => $this->attributor->attach($ticket, $release, TicketReleaseState::Shipped))
        ->toThrow(UnstableResolutionReleaseException::class);

    expect(TicketRelease::query()->count())->toBe(0);
});

test('shipping a ticket to a release with a stable tag is allowed', function (): void {
    $ticket = Ticket::factory()->create();
    $release = Release::factory()->create();
    $release->tags()->save(ReleaseTag::factory()->make()); // stable

    $ticketRelease = $this->attributor->attach($ticket, $release, TicketReleaseState::Shipped);

    expect($ticketRelease->state)->toBe(TicketReleaseState::Shipped);
});

test('marking a ticket affected accepts a tagless observed release', function (): void {
    $ticket = Ticket::factory()->create();
    $release = Release::factory()->observed()->create();

    $ticketRelease = $this->attributor->attach($ticket, $release, TicketReleaseState::Affected);

    expect($ticketRelease->state)->toBe(TicketReleaseState::Affected)
        ->and($release->effectiveMaturity())->toBeNull();
});

test('promising a ticket to an announced release does not require a stable tag', function (): void {
    $ticket = Ticket::factory()->create();
    $release = Release::factory()->create(); // announced, no tags

    $ticketRelease = $this->attributor->attach($ticket, $release, TicketReleaseState::Promised);

    expect($ticketRelease->state)->toBe(TicketReleaseState::Promised);
});
