<?php

declare(strict_types=1);

namespace Modules\SAO\Attribution;

use Modules\SAO\Enums\ReleaseTagKind;

/**
 * The result of reading a VCS tag: the release version it realizes (its stable
 * label, e.g. `1.4.0`, shared by `v1.4.0` and `v1.4.0-rc.1`) and whether the tag
 * is a stable or candidate realization.
 */
final readonly class ReleaseTagClassification
{
    public function __construct(
        public string $version,
        public ReleaseTagKind $kind,
    ) {}
}
