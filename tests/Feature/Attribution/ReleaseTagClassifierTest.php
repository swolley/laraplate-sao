<?php

declare(strict_types=1);

use Modules\SAO\Attribution\ReleaseTagClassifier;
use Modules\SAO\Enums\ReleaseTagKind;

beforeEach(function (): void {
    $this->classifier = new ReleaseTagClassifier();
});

test('it classifies tags into a version and a kind', function (string $tag, string $version, ReleaseTagKind $kind): void {
    $classification = $this->classifier->classify($tag);

    expect($classification->version)->toBe($version)
        ->and($classification->kind)->toBe($kind);
})->with([
    'v-prefixed stable' => ['v1.4.0', '1.4.0', ReleaseTagKind::Stable],
    'plain stable' => ['1.4.0', '1.4.0', ReleaseTagKind::Stable],
    'two-part stable' => ['v2.0', '2.0', ReleaseTagKind::Stable],
    'semver rc' => ['v1.4.0-rc.1', '1.4.0', ReleaseTagKind::Candidate],
    'semver beta' => ['2.0.0-beta', '2.0.0', ReleaseTagKind::Candidate],
    'build metadata is stable' => ['v1.4.0+build.5', '1.4.0', ReleaseTagKind::Stable],
    'non-semver plain' => ['nightly', 'nightly', ReleaseTagKind::Stable],
    'non-semver with marker' => ['release-snapshot', 'release-snapshot', ReleaseTagKind::Candidate],
]);

test('the pre-release marker set is configurable for non-semver tags', function (): void {
    config(['sao.attribution.prerelease_markers' => ['-edge']]);

    expect($this->classifier->classify('build-edge')->kind)->toBe(ReleaseTagKind::Candidate)
        ->and($this->classifier->classify('build-snapshot')->kind)->toBe(ReleaseTagKind::Stable);
});
