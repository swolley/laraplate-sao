<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Attribution\ReleaseAttributionService;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\ReleaseStatus;
use Modules\SAO\Enums\ReleaseTagKind;
use Modules\SAO\Enums\TicketReleaseState;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\ReleaseTag;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketRelease;
use Modules\SAO\Tests\Support\Drivers\StubReleasesDriver;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(ReleaseAttributionService::class);
});

function stubContext(): BindingContext
{
    return new BindingContext(new ConnectionContext(baseUrl: null, credentials: []));
}

test('a stable tag ships the release and the ticket attribution', function (): void {
    $ticket = Ticket::factory()->create();

    $ticketRelease = $this->service->attribute($ticket, 'abc123', new StubReleasesDriver('v1.4.0'), stubContext());

    $release = Release::query()->withoutGlobalScopes()->where('project_id', $ticket->project_id)->sole();
    $tag = ReleaseTag::query()->withoutGlobalScopes()->where('release_id', $release->id)->sole();

    expect($release->version)->toBe('1.4.0')
        ->and($release->status)->toBe(ReleaseStatus::Shipped)
        ->and($release->released_at)->not->toBeNull()
        ->and($tag->kind)->toBe(ReleaseTagKind::Stable)
        ->and($ticketRelease->state)->toBe(TicketReleaseState::Shipped);
});

test('a candidate tag only promises the release', function (): void {
    $ticket = Ticket::factory()->create();

    $ticketRelease = $this->service->attribute($ticket, 'abc123', new StubReleasesDriver('v2.0.0-rc.1'), stubContext());

    $release = Release::query()->withoutGlobalScopes()->where('project_id', $ticket->project_id)->sole();

    expect($release->version)->toBe('2.0.0')
        ->and($release->status)->toBe(ReleaseStatus::Announced)
        ->and($release->released_at)->toBeNull()
        ->and($ticketRelease->state)->toBe(TicketReleaseState::Promised);
});

test('a candidate then a stable tag of the same release promotes to shipped', function (): void {
    $ticket = Ticket::factory()->create();

    $this->service->attribute($ticket, 'abc123', new StubReleasesDriver('v1.4.0-rc.1'), stubContext());
    $this->service->attribute($ticket, 'abc123', new StubReleasesDriver('v1.4.0'), stubContext());

    $release = Release::query()->withoutGlobalScopes()->where('project_id', $ticket->project_id)->sole();

    expect(Release::query()->withoutGlobalScopes()->where('project_id', $ticket->project_id)->count())->toBe(1)
        ->and(ReleaseTag::query()->withoutGlobalScopes()->where('release_id', $release->id)->count())->toBe(2)
        ->and($release->status)->toBe(ReleaseStatus::Shipped)
        ->and(TicketRelease::query()->withoutGlobalScopes()->where('ticket_id', $ticket->id)->sole()->state)
        ->toBe(TicketReleaseState::Shipped);
});

test('a later candidate never demotes a shipped attribution', function (): void {
    $ticket = Ticket::factory()->create();

    $this->service->attribute($ticket, 'abc123', new StubReleasesDriver('v1.4.0'), stubContext());
    $this->service->attribute($ticket, 'abc123', new StubReleasesDriver('v1.4.0-rc.2'), stubContext());

    $release = Release::query()->withoutGlobalScopes()->where('project_id', $ticket->project_id)->sole();

    expect($release->status)->toBe(ReleaseStatus::Shipped)
        ->and(TicketRelease::query()->withoutGlobalScopes()->where('ticket_id', $ticket->id)->sole()->state)
        ->toBe(TicketReleaseState::Shipped);
});

test('no containing tag attributes nothing', function (): void {
    $ticket = Ticket::factory()->create();

    $result = $this->service->attribute($ticket, 'abc123', new StubReleasesDriver(null), stubContext());

    expect($result)->toBeNull()
        ->and(Release::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(TicketRelease::query()->withoutGlobalScopes()->count())->toBe(0);
});
