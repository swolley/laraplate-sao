<?php

declare(strict_types=1);

namespace Modules\SAO\Attribution;

use Modules\SAO\Enums\ReleaseStatus;
use Modules\SAO\Enums\ReleaseTagKind;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\ReleaseTag;

/**
 * Upserts a {@see Release} and its {@see ReleaseTag} from a VCS tag, normalizing
 * the version and promoting the release once a stable tag realizes it.
 *
 * The release version is always the normalized stable label, so a candidate (RC)
 * registers the future stable version — the release stays `announced`, realized
 * only by candidate tags, until a stable tag ships it (status → `shipped` + a
 * best-effort `released_at`). Promotion is monotonic and idempotent. The shared
 * core behind release attribution (per fixing commit) and release sync (per tag).
 */
final readonly class ReleaseRegistrar
{
    public function __construct(private ReleaseTagClassifier $classifier) {}

    /**
     * Register the tag against its (normalized) release. When the release does not
     * yet exist and `$createIfMissing` is false — release sync only touches
     * releases attribution already created — nothing is written and null returns.
     */
    public function register(int $projectId, string $tag, bool $createIfMissing): ?ReleaseRegistration
    {
        $classification = $this->classifier->classify($tag);
        $isStable = $classification->kind === ReleaseTagKind::Stable;

        $release = Release::query()->firstOrNew([
            'project_id' => $projectId,
            'version' => $classification->version,
        ]);

        if (! $release->exists && ! $createIfMissing) {
            return null;
        }

        $wasShipped = $release->exists && $release->status === ReleaseStatus::Shipped;

        if (! $release->exists) {
            $release->status = ReleaseStatus::Announced;
        }

        if ($isStable) {
            $release->status = ReleaseStatus::Shipped;
            $release->released_at ??= now();
        }

        $release->save();

        $releaseTag = ReleaseTag::query()->firstOrNew([
            'release_id' => $release->getKey(),
            'tag' => $tag,
        ]);
        $releaseTag->kind = $classification->kind;
        $releaseTag->save();

        return new ReleaseRegistration($release, promoted: $isStable && ! $wasShipped);
    }
}
