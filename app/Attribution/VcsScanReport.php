<?php

declare(strict_types=1);

namespace Modules\SAO\Attribution;

/**
 * The result of scanning a `vcs` binding's commits for ticket references: how many
 * commits were read, how many fix and mention links were written, how many
 * releases were attributed, any keys that resolved to no ticket, and whether the
 * page walk was truncated.
 */
final readonly class VcsScanReport
{
    /**
     * @param  list<string>  $unknownKeys
     */
    public function __construct(
        public bool $processed,
        public int $commitsScanned = 0,
        public int $fixLinks = 0,
        public int $mentionLinks = 0,
        public int $releasesAttributed = 0,
        public array $unknownKeys = [],
        public int $pages = 0,
        public bool $truncated = false,
    ) {}

    public static function skipped(): self
    {
        return new self(processed: false);
    }
}
