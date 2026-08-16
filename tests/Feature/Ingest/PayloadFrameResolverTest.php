<?php

declare(strict_types=1);

use Modules\Core\Logging\Fingerprint\Fingerprinter;
use Modules\Core\Logging\Fingerprint\FingerprintNormalizer;
use Modules\SAO\Ingest\PayloadFrameResolver;

it('recovers the signature from explicit payload fields', function (): void {
    $resolver = new PayloadFrameResolver(new Fingerprinter(FingerprintNormalizer::default()));

    $signature = $resolver->signature([
        'kind' => 'exception',
        'class' => 'RuntimeException',
        'file' => base_path('Modules/AI/app/Jobs/GenerateEmbeddingsJob.php'),
        'function' => 'handle',
        'message' => 'Model id=99 failed',
    ]);

    expect($signature['class'])->toBe('RuntimeException')
        ->and($signature['file'])->toBe('Modules/AI/app/Jobs/GenerateEmbeddingsJob.php')
        ->and($signature['module'])->toBe('AI');
});

it('recovers class, file and message from a flattened exception string', function (): void {
    $resolver = new PayloadFrameResolver(new Fingerprinter(FingerprintNormalizer::default()));

    $signature = $resolver->signature([
        'message' => "RuntimeException: Model id=99 failed in /var/www/Modules/AI/app/Jobs/GenerateEmbeddingsJob.php:89\nStack trace:\n#0 {main}",
    ]);

    expect($signature['class'])->toBe('RuntimeException')
        ->and($signature['file'])->toContain('GenerateEmbeddingsJob.php')
        ->and($signature['message'])->toBe('Model id=99 failed');
});

it('produces the shared fingerprint for a received error', function (): void {
    $fingerprinter = new Fingerprinter(FingerprintNormalizer::default());
    $resolver = new PayloadFrameResolver($fingerprinter);

    $payload = [
        'kind' => 'exception',
        'class' => 'RuntimeException',
        'file' => 'Modules/AI/app/Jobs/GenerateEmbeddingsJob.php',
        'function' => 'handle',
        'message' => 'Model id=99 failed',
    ];

    $expected = $fingerprinter->hash('exception', 'AI', 'RuntimeException', 'Modules/AI/app/Jobs/GenerateEmbeddingsJob.php', 'handle', 'Model id=99 failed');

    expect($resolver->key($payload))->toBe($expected);
});

it('is stable across value-position volatile detail', function (): void {
    $resolver = new PayloadFrameResolver(new Fingerprinter(FingerprintNormalizer::default()));

    $base = [
        'kind' => 'exception',
        'class' => 'QueryException',
        'file' => 'Modules/AI/app/Repo.php',
        'function' => 'find',
    ];

    $first = $resolver->key([...$base, 'message' => 'No result for id=17']);
    $second = $resolver->key([...$base, 'message' => 'No result for id=8823']);

    expect($first)->toBe($second);
});
