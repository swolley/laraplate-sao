<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\IngestStatus;
use Modules\SAO\Models\IngestEvent;
use Modules\SAO\Models\SourceProfile;

uses(RefreshDatabase::class);

test('a source profile stores its matchers and bindings as arrays', function (): void {
    $profile = SourceProfile::factory()->create()->fresh();

    expect($profile->matchers)->toBeArray()
        ->and($profile->field_bindings)->toBeArray()
        ->and($profile->is_active)->toBeTrue();
});

test('an ingest event records a status and casts its payload', function (): void {
    $event = IngestEvent::factory()->create([
        'payload' => ['a' => 1],
        'status' => IngestStatus::Ingested,
    ])->fresh();

    expect($event->payload)->toBe(['a' => 1])
        ->and($event->status)->toBe(IngestStatus::Ingested);
});

test('an ingest event resolves its optional relations', function (): void {
    $profile = SourceProfile::factory()->create();
    $event = IngestEvent::factory()->create(['source_profile_id' => $profile->id]);

    expect($event->sourceProfile->id)->toBe($profile->id)
        ->and($event->connection)->toBeNull()
        ->and($event->project)->toBeNull();
});
