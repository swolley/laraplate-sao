<?php

declare(strict_types=1);

namespace Modules\SAO\Data;

use Modules\SAO\Enums\SyncOutcome;

/**
 * The tally of one polling run over a binding: how many remote issues fell into
 * each {@see SyncOutcome}, how many pages were read, and whether the binding was
 * actually processed (a non-inbound or non-issues binding is reported as
 * `processed = false` rather than silently skipped). `truncated` is set when the
 * page cap was hit before the driver ran out of pages, so a bounded sweep is
 * never mistaken for a complete one.
 */
final readonly class SyncReport
{
    /**
     * @param  array<string, int>  $outcomes  SyncOutcome value → count.
     */
    public function __construct(
        public array $outcomes,
        public int $pages,
        public bool $processed,
        public bool $truncated = false,
    ) {}

    public static function skipped(): self
    {
        return new self([], 0, false);
    }

    public function count(SyncOutcome $outcome): int
    {
        return $this->outcomes[$outcome->value] ?? 0;
    }

    public function total(): int
    {
        return array_sum($this->outcomes);
    }
}
