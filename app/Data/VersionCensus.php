<?php

declare(strict_types=1);

namespace Modules\SAO\Data;

use Modules\SAO\Models\Release;

/**
 * The outcome of censusing a reported version: the normalized string to stamp on
 * an occurrence (null when nothing usable was reported) and the censused
 * {@see Release} it resolved to (null when the version was kept raw but not
 * promoted).
 */
final readonly class VersionCensus
{
    public function __construct(
        public ?string $affectedVersion,
        public ?Release $release,
    ) {}

    public static function empty(): self
    {
        return new self(null, null);
    }
}
