<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\ReleaseStatus;
use Modules\SAO\Ingest\SignalIngestService;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Release;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(SignalIngestService::class);
    $this->project = Project::factory()->create();
});

test('ingest records the reported version on the occurrence and censuses it', function (): void {
    $signal = $this->service->ingest($this->project, [
        'message' => 'Boom',
        'version' => 'v1.4.0',
    ]);

    $occurrence = $signal->occurrences()->sole();
    $release = Release::query()->sole();

    expect($occurrence->affected_version)->toBe('1.4.0')
        ->and($occurrence->affected_release_id)->toBe($release->getKey())
        ->and($release->status)->toBe(ReleaseStatus::Observed)
        ->and($release->version)->toBe('1.4.0');
});

test('ingest keeps an unnormalizable version raw without censusing it', function (): void {
    $signal = $this->service->ingest($this->project, [
        'message' => 'Boom',
        'version' => 'nightly',
    ]);

    expect($signal->occurrences()->sole()->affected_version)->toBe('nightly')
        ->and($signal->occurrences()->sole()->affected_release_id)->toBeNull()
        ->and(Release::query()->count())->toBe(0);
});

test('ingest without a reported version leaves the occurrence version empty', function (): void {
    $signal = $this->service->ingest($this->project, ['message' => 'Boom']);

    expect($signal->occurrences()->sole()->affected_version)->toBeNull()
        ->and($signal->occurrences()->sole()->affected_release_id)->toBeNull();
});
