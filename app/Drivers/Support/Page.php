<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Support;

/**
 * One page of a capability's list operation.
 *
 * Every list endpoint paginates (spec §5): a driver returns items plus an opaque
 * `nextCursor` (null when exhausted). Callers loop until `hasMore()` is false so
 * a driver can never silently read only the first page.
 */
final readonly class Page
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor = null,
    ) {}

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }
}
