<?php

declare(strict_types=1);

namespace Modules\SAO\Attribution;

/**
 * The result of syncing a `releases` binding's tags: how many tags were read, how
 * many matched a known release (and were registered), how many announced releases
 * were promoted to shipped by a newly-seen stable tag, and whether the page walk
 * was truncated.
 */
final readonly class ReleaseSyncReport
{
    public function __construct(
        public bool $processed,
        public int $tagsScanned = 0,
        public int $tagsRegistered = 0,
        public int $releasesPromoted = 0,
        public int $pages = 0,
        public bool $truncated = false,
    ) {}

    public static function skipped(): self
    {
        return new self(processed: false);
    }
}
