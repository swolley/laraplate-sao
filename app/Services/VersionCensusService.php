<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Data\VersionCensus;
use Modules\SAO\Enums\ReleaseStatus;
use Modules\SAO\Ingest\VersionNormalizer;
use Modules\SAO\Models\Release;

/**
 * Records a reported version into the per-project version census. A normalizable
 * version is deduped into a single {@see Release} row per product version,
 * created as `observed` when new — never downgrading a release a maintainer has
 * already curated. An unnormalizable string is kept raw for audit but not
 * promoted, so garbage cannot fill the census with near-duplicates.
 */
final readonly class VersionCensusService
{
    public function __construct(private VersionNormalizer $normalizer) {}

    public function record(int $projectId, ?string $rawVersion): VersionCensus
    {
        if ($rawVersion === null) {
            return VersionCensus::empty();
        }

        $normalized = $this->normalizer->normalize($rawVersion);

        if ($normalized === null) {
            $raw = mb_trim($rawVersion);

            return new VersionCensus($raw === '' ? null : $raw, null);
        }

        $release = Release::query()->firstOrCreate(
            [
                'project_id' => $projectId,
                'version' => $this->normalizer->productVersion($normalized),
            ],
            ['status' => ReleaseStatus::Observed],
        );

        return new VersionCensus($normalized, $release);
    }
}
