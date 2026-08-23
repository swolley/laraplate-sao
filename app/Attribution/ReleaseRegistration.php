<?php

declare(strict_types=1);

namespace Modules\SAO\Attribution;

use Modules\SAO\Models\Release;

/**
 * The result of registering a tag against a release: the release it resolved to
 * and whether this registration promoted it from announced to shipped (a stable
 * tag arriving for a version so far known only from a candidate).
 */
final readonly class ReleaseRegistration
{
    public function __construct(
        public Release $release,
        public bool $promoted,
    ) {}
}
