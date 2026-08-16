<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Modules\SAO\Ingest\InternalLogSource;
use Modules\SAO\Ingest\PipelineContext;
use Modules\SAO\Ingest\SignalIngestService;
use Modules\SAO\Models\Project;

uses(RefreshDatabase::class);

test('the pipeline context stamps logs emitted while it is active', function (): void {
    $context = new PipelineContext;

    expect($context->stamp(['a' => 1]))->toBe(['a' => 1]);

    $stamped = $context->run(fn (): array => $context->stamp(['a' => 1]));

    expect($stamped)->toBe(['a' => 1, PipelineContext::ORIGIN_KEY => true])
        ->and($context->isActive())->toBeFalse();
});

test('the internal log source discards pipeline-originated records regardless of module', function (): void {
    $source = new InternalLogSource;

    $result = $source->select([
        ['message' => 'real error', 'context' => ['module' => 'AI']],
        ['message' => 'pipeline noise', 'context' => ['module' => 'AI', PipelineContext::ORIGIN_KEY => true]],
        ['message' => 'pipeline noise from core', 'context' => ['module' => 'Core', PipelineContext::ORIGIN_KEY => true]],
    ]);

    expect($result['ingestible'])->toHaveCount(1)
        ->and($result['ingestible'][0]['message'])->toBe('real error')
        ->and($result['discarded'])->toHaveCount(2)
        ->and($result['discarded'][0]['reason'])->toBe(InternalLogSource::DISCARD_PIPELINE_ORIGIN);
});

test('the per-group rate limiter caps occurrences within the window', function (): void {
    Config::set('sao.signals.max_occurrences_per_window', 3);
    Config::set('sao.signals.window_minutes', 60);

    $project = Project::factory()->create();
    $service = app(SignalIngestService::class);

    $payload = [
        'kind' => 'exception',
        'class' => 'RuntimeException',
        'file' => 'Modules/AI/app/Loop.php',
        'function' => 'run',
        'message' => 'looping',
    ];

    $signal = null;

    foreach (range(1, 5) as $ignored) {
        $signal = $service->ingest($project, $payload);
    }

    expect($signal->fresh()->occurrence_count)->toBe(3)
        ->and($signal->occurrences()->count())->toBe(3);
});
