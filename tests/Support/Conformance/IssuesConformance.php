<?php

declare(strict_types=1);

namespace Modules\SAO\Tests\Support\Conformance;

use Modules\SAO\Drivers\Contracts\IssuesCapability;
use Modules\SAO\Drivers\Support\BindingContext;

/**
 * The battery every `issues` driver must pass. A driver is not done when it
 * works; it is done when it passes conformance (spec §12).
 */
final class IssuesConformance
{
    public static function assert(IssuesCapability $driver, BindingContext $context): void
    {
        self::assertPaginatesBeyondOnePage($driver, $context);
        self::assertLookup($driver, $context);
        self::assertWrites($driver, $context);
        self::assertStatusTranslationUsesTheMap($driver);
    }

    private static function assertPaginatesBeyondOnePage(IssuesCapability $driver, BindingContext $context): void
    {
        $ids = [];
        $cursor = null;
        $pages = 0;

        do {
            $page = $driver->list($context, $cursor);

            foreach ($page->items as $item) {
                $ids[] = $item['id'] ?? null;
            }

            $cursor = $page->nextCursor;
            $pages++;
        } while ($cursor !== null && $pages < 100);

        // More than one page proves cursor following, not a first-page-only read.
        expect($pages)->toBeGreaterThan(1)
            ->and($ids)->toBe(array_values(array_unique($ids)))
            ->and($ids)->not->toContain(null);
    }

    private static function assertLookup(IssuesCapability $driver, BindingContext $context): void
    {
        $first = $driver->list($context)->items[0]['id'];

        expect($driver->lookup($context, (string) $first))->toBeArray()
            ->and($driver->lookup($context, 'does-not-exist'))->toBeNull();
    }

    private static function assertWrites(IssuesCapability $driver, BindingContext $context): void
    {
        $created = $driver->create($context, ['title' => 'Conformance']);
        expect($created)->toHaveKey('id');

        $updated = $driver->update($context, (string) $created['id'], ['title' => 'Changed']);
        expect($updated['title'])->toBe('Changed');

        $driver->comment($context, (string) $created['id'], 'a comment');
    }

    private static function assertStatusTranslationUsesTheMap(IssuesCapability $driver): void
    {
        expect($driver->translateStatus(['Done' => 'resolved'], 'Done'))->toBe('resolved')
            ->and($driver->translateStatus(['Done' => 'resolved'], 'Unmapped'))->toBeNull();
    }
}
