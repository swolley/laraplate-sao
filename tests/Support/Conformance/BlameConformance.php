<?php

declare(strict_types=1);

namespace Modules\SAO\Tests\Support\Conformance;

use Modules\SAO\Drivers\Contracts\BlameCapability;
use Modules\SAO\Drivers\Support\BindingContext;

/**
 * The battery every `blame` driver must pass: a file's blame returns a
 * per-author line tally whose entries carry the normalized shape.
 */
final class BlameConformance
{
    public static function assert(BlameCapability $driver, BindingContext $context): void
    {
        $tally = $driver->blame($context, 'app/Example.php', 'main');

        expect($tally)->toBeArray()->not->toBeEmpty();

        foreach ($tally as $entry) {
            expect($entry)->toHaveKeys(['author', 'author_email', 'lines'])
                ->and($entry['lines'])->toBeInt()->toBeGreaterThan(0);
        }
    }
}
