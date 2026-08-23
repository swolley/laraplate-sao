<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Models\Signal;
use Modules\SAO\Models\SignalOccurrence;

uses(RefreshDatabase::class);

test('dry-run reports without deleting, then a real run prunes', function (): void {
    $signal = Signal::factory()->create();
    SignalOccurrence::factory()->count(2)->create(['signal_id' => $signal->id, 'occurred_at' => now()->subDays(200)]);

    $this->artisan('sao:prune', ['--dry-run' => true])
        ->expectsOutputToContain('would be removed')
        ->assertSuccessful();

    expect(SignalOccurrence::query()->count())->toBe(2);

    $this->artisan('sao:prune')->assertSuccessful();

    expect(SignalOccurrence::query()->count())->toBe(0);
});
