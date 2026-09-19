<?php

declare(strict_types=1);

use Modules\SAO\Ingest\VersionNormalizer;

beforeEach(function (): void {
    $this->normalizer = new VersionNormalizer();
});

test('it normalizes reported version strings to a canonical token', function (?string $expected, string $raw): void {
    expect($this->normalizer->normalize($raw))->toBe($expected);
})->with([
    'v prefix' => ['1.4.0', 'v1.4.0'],
    'plain' => ['1.4.0', '1.4.0'],
    'build suffix noise' => ['1.4.0', '1.4.0 (build 123)'],
    'pre-release kept' => ['1.4.0-rc.1', 'v1.4.0-rc.1'],
    'two-part' => ['2.0', '2.0'],
    'non-numeric is unnormalizable' => [null, 'nightly'],
    'blank is unnormalizable' => [null, '   '],
]);

test('the product version rolls a pre-release up to its release core', function (): void {
    expect($this->normalizer->productVersion('1.4.0-rc.1'))->toBe('1.4.0')
        ->and($this->normalizer->productVersion('1.4.0'))->toBe('1.4.0');
});
