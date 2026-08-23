<?php

declare(strict_types=1);

namespace Modules\SAO\Data;

/**
 * The result of importing an external tracker's history into a binding: tickets
 * created, existing links updated, issues skipped by the open-only filter, issues
 * with no usable remote id, comments and attachments imported, and whether the
 * page walk was truncated.
 */
final readonly class TrackerImportReport
{
    public function __construct(
        public bool $processed,
        public int $created = 0,
        public int $updated = 0,
        public int $filtered = 0,
        public int $skipped = 0,
        public int $comments = 0,
        public int $attachments = 0,
        public int $pages = 0,
        public bool $truncated = false,
    ) {}

    public static function skipped(): self
    {
        return new self(processed: false);
    }

    public function total(): int
    {
        return $this->created + $this->updated + $this->filtered + $this->skipped;
    }
}
