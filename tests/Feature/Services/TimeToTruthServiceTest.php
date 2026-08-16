<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\ChangeRefType;
use Modules\SAO\Enums\TicketReleaseState;
use Modules\SAO\Models\ChangeRef;
use Modules\SAO\Models\ClosureAudit;
use Modules\SAO\Models\Environment;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\Signal;
use Modules\SAO\Models\SignalOccurrence;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketRelease;
use Modules\SAO\Services\FixStatusResolver;
use Modules\SAO\Services\TimeToTruthService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new TimeToTruthService(new FixStatusResolver());
});

test('a signal with no ticket yields all-null intervals', function (): void {
    $signal = Signal::factory()->create(['ticket_id' => null, 'first_seen_at' => now()]);

    $ttt = $this->service->forSignal($signal);

    expect($ttt->time_to_fix_merged_seconds)->toBeNull()
        ->and($ttt->time_to_deploy_gap_known_seconds)->toBeNull()
        ->and($ttt->time_to_premature_reopen_seconds)->toBeNull();
});

test('the intervals are measured from the first sighting', function (): void {
    $firstSeen = CarbonImmutable::parse('2026-08-01 00:00:00');
    $project = Project::factory()->create();
    $ticket = Ticket::factory()->for($project)->create();

    ChangeRef::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ChangeRefType::PullRequest,
        'identifier' => '5',
        'merged_at' => CarbonImmutable::parse('2026-08-03 00:00:00'),
    ]);

    $release = Release::factory()->for($project)->shipped()->create([
        'version' => '1.4.0',
        'released_at' => CarbonImmutable::parse('2026-08-04 00:00:00'),
    ]);
    TicketRelease::factory()->create([
        'ticket_id' => $ticket->id,
        'release_id' => $release->id,
        'state' => TicketReleaseState::Shipped,
    ]);
    Environment::factory()->for($project)->create(['name' => 'production', 'current_version' => '1.3.0']);

    $signal = Signal::factory()->for($project)->create(['ticket_id' => $ticket->id, 'first_seen_at' => $firstSeen]);
    $audit = ClosureAudit::factory()->create([
        'ticket_id' => $ticket->id,
        'closed_at' => CarbonImmutable::parse('2026-08-05 00:00:00'),
        'reopened_at' => CarbonImmutable::parse('2026-08-06 00:00:00'),
        'is_premature' => true,
        'returned_occurrence_id' => SignalOccurrence::factory()->for($signal)->create()->id,
    ]);

    $ttt = $this->service->forSignal($signal->refresh());

    expect($ttt->time_to_fix_merged_seconds)->toBe(2 * 86400)
        ->and($ttt->time_to_deploy_gap_known_seconds)->toBe(3 * 86400)
        ->and($ttt->time_to_premature_reopen_seconds)->toBe(5 * 86400);
});

test('no deploy gap is reported once the fix is deployed everywhere', function (): void {
    $firstSeen = CarbonImmutable::parse('2026-08-01 00:00:00');
    $project = Project::factory()->create();
    $ticket = Ticket::factory()->for($project)->create();
    $release = Release::factory()->for($project)->shipped()->create([
        'version' => '2.0.0',
        'released_at' => CarbonImmutable::parse('2026-08-02 00:00:00'),
    ]);
    TicketRelease::factory()->create([
        'ticket_id' => $ticket->id,
        'release_id' => $release->id,
        'state' => TicketReleaseState::Shipped,
    ]);
    Environment::factory()->for($project)->create(['name' => 'production', 'current_version' => '2.0.0']);

    $signal = Signal::factory()->for($project)->create(['ticket_id' => $ticket->id, 'first_seen_at' => $firstSeen]);

    expect($this->service->forSignal($signal->refresh())->time_to_deploy_gap_known_seconds)->toBeNull();
});
