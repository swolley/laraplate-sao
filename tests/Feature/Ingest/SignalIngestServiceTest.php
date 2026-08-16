<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\SignalState;
use Modules\SAO\Ingest\SignalIngestService;
use Modules\SAO\Models\Project;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function sao_error_payload(array $overrides = []): array
{
    return array_merge([
        'kind' => 'exception',
        'class' => 'RuntimeException',
        'file' => 'Modules/AI/app/Jobs/GenerateEmbeddingsJob.php',
        'function' => 'handle',
        'message' => 'Model failed id=1',
        'environment' => 'production',
    ], $overrides);
}

test('a native key wins and is namespaced by source', function (): void {
    $project = Project::factory()->create();

    $signal = app(SignalIngestService::class)->ingest($project, [
        'native_key' => 'issue-42',
        'source' => 'sentry',
        'message' => 'anything',
    ]);

    expect($signal->group_key)->toBe('sentry:issue-42');
});

test('two matching payloads recur one signal and count two occurrences', function (): void {
    $project = Project::factory()->create();
    $service = app(SignalIngestService::class);

    $first = $service->ingest($project, sao_error_payload());
    $second = $service->ingest($project, sao_error_payload(['message' => 'Model failed id=999']));

    expect($second->id)->toBe($first->id)
        ->and($first->fresh()->occurrence_count)->toBe(2)
        ->and($first->occurrences()->count())->toBe(2);
});

test('the same error in two projects makes two signals with the same group key', function (): void {
    $service = app(SignalIngestService::class);
    $alpha = Project::factory()->create();
    $beta = Project::factory()->create();

    $signalAlpha = $service->ingest($alpha, sao_error_payload());
    $signalBeta = $service->ingest($beta, sao_error_payload());

    expect($signalAlpha->id)->not->toBe($signalBeta->id)
        ->and($signalAlpha->group_key)->toBe($signalBeta->group_key);
});

test('a resolved signal reopens when the error recurs', function (): void {
    $project = Project::factory()->create();
    $service = app(SignalIngestService::class);

    $signal = $service->ingest($project, sao_error_payload());
    $signal->update(['state' => SignalState::Resolved]);

    $reopened = $service->ingest($project, sao_error_payload());

    expect($reopened->fresh()->state)->toBe(SignalState::Open);
});
