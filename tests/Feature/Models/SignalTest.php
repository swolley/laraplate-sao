<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\SignalState;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Signal;
use Modules\SAO\Models\SignalAlias;
use Modules\SAO\Models\SignalOccurrence;

uses(RefreshDatabase::class);

test('a signal persists with an algo version and opens by default', function (): void {
    $signal = Signal::factory()->create()->fresh();

    expect($signal->algo_version)->toBe(1)
        ->and($signal->state)->toBe(SignalState::Open);
});

test('a group key is unique within a project but repeatable across projects', function (): void {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();

    Signal::factory()->for($projectA)->create(['group_key' => 'abc123']);
    Signal::factory()->for($projectB)->create(['group_key' => 'abc123']);

    expect(fn (): Signal => Signal::factory()->for($projectA)->create(['group_key' => 'abc123']))
        ->toThrow(QueryException::class);
});

test('a signal counts its occurrences', function (): void {
    $signal = Signal::factory()->create();
    SignalOccurrence::factory()->count(3)->create(['signal_id' => $signal->id]);

    expect($signal->occurrences()->count())->toBe(3);
});

test('an alias maps a superseded key to its signal', function (): void {
    $signal = Signal::factory()->create();
    $alias = SignalAlias::factory()->create([
        'signal_id' => $signal->id,
        'group_key' => 'old-key',
    ]);

    expect($alias->signal->id)->toBe($signal->id)
        ->and($signal->aliases()->pluck('group_key')->all())->toBe(['old-key']);
});
