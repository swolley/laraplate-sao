<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\ChangeRefType;
use Modules\SAO\Enums\TicketReleaseState;
use Modules\SAO\Models\ChangeRef;
use Modules\SAO\Models\Environment;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\Signal;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketRelease;
use Modules\SAO\Services\FixStatusResolver;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->resolver = new FixStatusResolver();
});

test('a signal can be correlated with a ticket and back', function (): void {
    $ticket = Ticket::factory()->create();
    $signal = Signal::factory()->create(['ticket_id' => $ticket->id]);

    expect($signal->ticket->is($ticket))->toBeTrue()
        ->and($ticket->signals()->pluck('id')->all())->toBe([$signal->id]);
});

test('the merged pull requests scope selects only merged pull-request refs', function (): void {
    $ticket = Ticket::factory()->create();
    ChangeRef::factory()->create(['ticket_id' => $ticket->id, 'type' => ChangeRefType::Commit, 'identifier' => 'abc']);
    ChangeRef::factory()->create(['ticket_id' => $ticket->id, 'type' => ChangeRefType::PullRequest, 'identifier' => '7', 'merged_at' => null]);
    $merged = ChangeRef::factory()->create(['ticket_id' => $ticket->id, 'type' => ChangeRefType::PullRequest, 'identifier' => '8', 'merged_at' => now()]);

    expect(ChangeRef::query()->mergedPullRequests()->pluck('id')->all())->toBe([$merged->id])
        ->and($merged->isMergedPullRequest())->toBeTrue();
});

test('a fix released but deployed only to staging is reported as missing on production', function (): void {
    $project = Project::factory()->create();
    $ticket = Ticket::factory()->for($project)->create();
    ChangeRef::factory()->create(['ticket_id' => $ticket->id, 'type' => ChangeRefType::PullRequest, 'identifier' => '9', 'merged_at' => now()]);

    $release = Release::factory()->for($project)->shipped()->create(['version' => '1.4.0']);
    TicketRelease::factory()->create([
        'ticket_id' => $ticket->id,
        'release_id' => $release->id,
        'state' => TicketReleaseState::Shipped,
    ]);

    Environment::factory()->for($project)->create(['name' => 'staging', 'current_version' => '1.4.0']);
    Environment::factory()->for($project)->create(['name' => 'production', 'current_version' => '1.3.9']);

    $status = $this->resolver->forTicket($ticket->refresh(), 'production');

    expect($status->pull_request_merged)->toBeTrue()
        ->and($status->fix_released)->toBeTrue()
        ->and($status->released_version)->toBe('1.4.0')
        ->and($status->deployed_environments)->toBe(['staging'])
        ->and($status->missing_environments)->toBe(['production'])
        ->and($status->deployed_there)->toBeFalse();
});

test('without a shipped release the fix is not released', function (): void {
    $project = Project::factory()->create();
    $ticket = Ticket::factory()->for($project)->create();
    $release = Release::factory()->for($project)->create(['version' => '2.0.0']);
    TicketRelease::factory()->create([
        'ticket_id' => $ticket->id,
        'release_id' => $release->id,
        'state' => TicketReleaseState::Promised,
    ]);

    $status = $this->resolver->forTicket($ticket->refresh(), 'production');

    expect($status->fix_released)->toBeFalse()
        ->and($status->released_version)->toBeNull()
        ->and($status->deployed_there)->toBeFalse();
});
