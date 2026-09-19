<?php

declare(strict_types=1);

namespace Modules\SAO\Ingest;

/**
 * Normalizes a raw software version reported in a log payload into a canonical
 * string, and derives the product-version label used to census it.
 *
 * A version is the first version-like token in the string — a numeric core with
 * an optional pre-release segment — after a leading `v` is dropped, so
 * `v1.4.0`, `1.4.0 (build 123)` and `1.4.0-rc.1` normalize to `1.4.0`, `1.4.0`
 * and `1.4.0-rc.1`. A string with no such token (e.g. `nightly`) is
 * unnormalizable: it is kept raw on the occurrence but never promoted to the
 * census, so near-duplicate garbage cannot flood it.
 */
final class VersionNormalizer
{
    public function normalize(string $raw): ?string
    {
        $trimmed = mb_trim($raw);

        if ($trimmed === '') {
            return null;
        }

        $stripped = preg_replace('/^v/i', '', $trimmed) ?? $trimmed;

        if (preg_match('/\d+(?:\.\d+)*(?:-[0-9A-Za-z.]+)?/', $stripped, $matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    /**
     * The product-version label a normalized version is censused under: the
     * numeric core, so a pre-release (`1.4.0-rc.1`) rolls up to its release
     * (`1.4.0`), one census row per product version.
     */
    public function productVersion(string $normalized): string
    {
        if (preg_match('/^\d+(?:\.\d+)*/', $normalized, $matches) === 1) {
            return $matches[0];
        }

        return $normalized;
    }
}
