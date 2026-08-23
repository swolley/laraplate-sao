<?php

declare(strict_types=1);

namespace Modules\SAO\Attribution;

use Modules\SAO\Enums\ReleaseTagKind;

/**
 * Reads a VCS tag into the release version it realizes and whether it is a stable
 * or candidate tag, by a semver heuristic.
 *
 * A semver pre-release segment (`1.4.0-rc.1`, `2.0.0-beta`) marks a candidate and
 * the version is the numeric core (`1.4.0`, `2.0.0`); build metadata (`+build.5`)
 * does not. A plain numeric tag is stable. A non-semver tag is stable unless it
 * carries one of the configured pre-release markers
 * (`sao.attribution.prerelease_markers`), and keeps its own text as the version.
 */
final class ReleaseTagClassifier
{
    public function classify(string $tag): ReleaseTagClassification
    {
        $normalized = preg_replace('/^v/i', '', trim($tag)) ?? $tag;

        if (preg_match('/^(\d+(?:\.\d+)*)(-[0-9A-Za-z.-]+)?(\+[0-9A-Za-z.-]+)?$/', $normalized, $matches) === 1) {
            $isCandidate = ($matches[2] ?? '') !== '';

            return new ReleaseTagClassification(
                version: $matches[1],
                kind: $isCandidate ? ReleaseTagKind::Candidate : ReleaseTagKind::Stable,
            );
        }

        return new ReleaseTagClassification(
            version: $normalized,
            kind: $this->hasPreReleaseMarker($normalized) ? ReleaseTagKind::Candidate : ReleaseTagKind::Stable,
        );
    }

    private function hasPreReleaseMarker(string $tag): bool
    {
        $lower = mb_strtolower($tag);

        foreach ($this->preReleaseMarkers() as $marker) {
            if ($marker !== '' && str_contains($lower, mb_strtolower($marker))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function preReleaseMarkers(): array
    {
        /** @var list<string> $markers */
        $markers = (array) config('sao.attribution.prerelease_markers', [
            '-rc', '-beta', '-alpha', '-pre', '-dev', '-snapshot',
        ]);

        return $markers;
    }
}
