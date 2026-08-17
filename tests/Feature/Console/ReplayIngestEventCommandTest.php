<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\IngestStatus;
use Modules\SAO\Models\IngestEvent;
use Modules\SAO\Models\SourceProfile;

uses(RefreshDatabase::class);

function replayProfile(): SourceProfile
{
    return SourceProfile::factory()->create([
        'name' => 'glitchtip',
        'matchers' => [['path' => 'source', 'operator' => 'equals', 'value' => 'glitchtip']],
        'field_bindings' => [
            'message' => 'error.message',
            'class' => 'error.type',
        ],
    ]);
}

/**
 * @param  array<string, mixed>  $payload
 */
function replayEvent(array $payload, ?int $profileId): IngestEvent
{
    return IngestEvent::factory()->create([
        'delivery_id' => 'del-' . uniqid(),
        'payload' => $payload,
        'source_profile_id' => $profileId,
        'status' => IngestStatus::Received,
    ]);
}

test('it dry-runs an event against its recorded profile and reports a match', function (): void {
    $profile = replayProfile();
    $event = replayEvent(['source' => 'glitchtip', 'error' => ['message' => 'boom', 'type' => 'RuntimeException']], $profile->id);

    $this->artisan('sao:ingest:replay', ['event' => $event->id])
        ->assertSuccessful()
        ->expectsOutputToContain('would_be_status');
});

test('it can replay against a different profile via --profile', function (): void {
    $recorded = replayProfile();
    $other = SourceProfile::factory()->create([
        'name' => 'never-matches',
        'matchers' => [['path' => 'source', 'operator' => 'equals', 'value' => 'nope']],
        'field_bindings' => ['message' => 'error.message'],
    ]);
    $event = replayEvent(['source' => 'glitchtip'], $recorded->id);

    // The chosen profile does not match this payload — a pure dry-run, nothing written.
    $this->artisan('sao:ingest:replay', ['event' => $event->id, '--profile' => (string) $other->id])
        ->assertSuccessful();

    expect(IngestEvent::query()->count())->toBe(1);
});

test('it fails when the event does not exist', function (): void {
    $this->artisan('sao:ingest:replay', ['event' => '999999'])->assertFailed();
});

test('it fails when the event has no profile and none is given', function (): void {
    $event = replayEvent(['source' => 'glitchtip'], null);

    $this->artisan('sao:ingest:replay', ['event' => $event->id])->assertFailed();
});
