<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Data\ReleaseHealth;
use Modules\SAO\Enums\ReleaseHealthVerdict;
use Modules\SAO\Models\Deployment;
use Modules\SAO\Models\Environment;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\Signal;
use Modules\SAO\Models\SignalOccurrence;
use Modules\SAO\Services\ReleaseHealthService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new ReleaseHealthService();
});

function seedOccurrences(Signal $signal, int $count, \Carbon\CarbonInterface $at, string $environment = 'production'): void
{
    SignalOccurrence::factory()->count($count)->create([
        'signal_id' => $signal->id,
        'environment' => $environment,
        'occurred_at' => $at,
    ]);
}

test('a release that was never shipped has no window to judge', function (): void {
    $release = Release::factory()->create(['released_at' => null]);

    $health = $this->service->forRelease($release);

    expect($health)->toBeInstanceOf(ReleaseHealth::class)
        ->and($health->verdict)->toBe(ReleaseHealthVerdict::Unknown)
        ->and($health->windowStart)->toBeNull()
        ->and($health->baseline)->toBeNull();
});

test('a shipped release with no signals and no baseline is unknown, not a false green', function (): void {
    $project = Project::factory()->create();
    $release = Release::factory()->for($project)->create(['released_at' => now()->subDays(2)]);

    $health = $this->service->forRelease($release);

    expect($health->verdict)->toBe(ReleaseHealthVerdict::Unknown)
        ->and($health->totalOccurrences)->toBe(0)
        ->and($health->newSignals)->toBe([])
        ->and($health->regressedSignals)->toBe([]);
});

test('existing signals at a flat rate versus the baseline read as healthy', function (): void {
    $project = Project::factory()->create();
    Release::factory()->for($project)->create(['released_at' => now()->subDays(10)]);
    $release = Release::factory()->for($project)->create(['released_at' => now()->subDays(2)]);

    $signal = Signal::factory()->for($project)->create([
        'group_key' => 'flat-signal',
        'first_seen_at' => now()->subDays(30),
    ]);

    seedOccurrences($signal, 2, now()->subDay());          // current window
    seedOccurrences($signal, 2, now()->subDays(9));        // baseline window

    $health = $this->service->forRelease($release);

    expect($health->verdict)->toBe(ReleaseHealthVerdict::Healthy)
        ->and($health->newSignals)->toBe([])
        ->and($health->regressedSignals)->toBe([])
        ->and($health->baseline)->not->toBeNull()
        ->and($health->totalOccurrences)->toBe(2);
});

test('a single new signal below the threshold reads as degraded', function (): void {
    $project = Project::factory()->create();
    $release = Release::factory()->for($project)->create(['released_at' => now()->subDays(2)]);

    $signal = Signal::factory()->for($project)->create([
        'group_key' => 'fresh-signal',
        'first_seen_at' => now()->subDay(),
    ]);
    seedOccurrences($signal, 1, now()->subDay());

    $health = $this->service->forRelease($release);

    expect($health->verdict)->toBe(ReleaseHealthVerdict::Degraded)
        ->and($health->newSignals)->toBe(['fresh-signal'])
        ->and($health->regressedSignals)->toBe([]);
});

test('enough brand-new signals tip the verdict to regressed', function (): void {
    $project = Project::factory()->create();
    $release = Release::factory()->for($project)->create(['released_at' => now()->subDays(2)]);

    foreach (['new-a', 'new-b', 'new-c'] as $key) {
        $signal = Signal::factory()->for($project)->create([
            'group_key' => $key,
            'first_seen_at' => now()->subDay(),
        ]);
        seedOccurrences($signal, 1, now()->subDay());
    }

    $health = $this->service->forRelease($release);

    expect($health->verdict)->toBe(ReleaseHealthVerdict::Regressed)
        ->and($health->newSignals)->toHaveCount(3);
});

test('an existing signal whose rate jumps past the baseline is a regression', function (): void {
    $project = Project::factory()->create();
    Release::factory()->for($project)->create(['released_at' => now()->subDays(10)]);
    $release = Release::factory()->for($project)->create(['released_at' => now()->subDays(2)]);

    $signal = Signal::factory()->for($project)->create([
        'group_key' => 'noisy-signal',
        'first_seen_at' => now()->subDays(30),
    ]);

    seedOccurrences($signal, 1, now()->subDays(9));   // baseline window: 1
    seedOccurrences($signal, 4, now()->subDay());     // current window: 4 (>= 3, > 1.5x)

    $health = $this->service->forRelease($release);

    expect($health->verdict)->toBe(ReleaseHealthVerdict::Regressed)
        ->and($health->regressedSignals)->toBe(['noisy-signal'])
        ->and($health->newSignals)->toBe([])
        ->and($health->occurrenceDeltaPct)->toBeGreaterThan(0.0);
});

test('a succeeded deployment finished_at anchors the window over released_at', function (): void {
    $project = Project::factory()->create();
    $release = Release::factory()->for($project)->create([
        'version' => '5.0.0',
        'released_at' => now()->subDays(20),
    ]);

    Deployment::factory()->for($project)->succeeded()->create([
        'release_id' => $release->id,
        'version' => '5.0.0',
        'finished_at' => now()->subDays(3),
    ]);

    // First seen after released_at but before the deploy finished: the release
    // window must start at the deploy, so this signal is not "new" in-window.
    $preDeploy = Signal::factory()->for($project)->create([
        'group_key' => 'pre-deploy',
        'first_seen_at' => now()->subDays(10),
    ]);
    seedOccurrences($preDeploy, 1, now()->subDays(2));

    $health = $this->service->forRelease($release);

    expect($health->windowStart->toDateString())->toBe(now()->subDays(3)->toDateString())
        ->and($health->newSignals)->toBe([]);
});

test('the verdict is scoped to the requested environment', function (): void {
    $project = Project::factory()->create();
    $release = Release::factory()->for($project)->create(['released_at' => now()->subDays(2)]);

    $prodSignal = Signal::factory()->for($project)->create([
        'group_key' => 'prod-only',
        'first_seen_at' => now()->subDay(),
    ]);
    seedOccurrences($prodSignal, 1, now()->subDay(), 'production');

    $stagingSignal = Signal::factory()->for($project)->create([
        'group_key' => 'staging-only',
        'first_seen_at' => now()->subDay(),
    ]);
    seedOccurrences($stagingSignal, 1, now()->subDay(), 'staging');

    $production = Environment::factory()->for($project)->create(['name' => 'production']);

    $health = $this->service->forRelease($release, $production);

    expect($health->verdict)->toBe(ReleaseHealthVerdict::Degraded)
        ->and($health->newSignals)->toBe(['prod-only'])
        ->and($health->totalOccurrences)->toBe(1);
});
