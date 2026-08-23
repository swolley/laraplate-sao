<?php

declare(strict_types=1);

namespace Modules\SAO\Tests\Support\Drivers;

use Modules\SAO\Drivers\Contracts\ReleasesCapability;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\Page;

/**
 * A network-free `releases` stub: `firstTagContaining` returns a fixed tag set at
 * construction, so release-attribution logic can be exercised offline without a
 * real Git host.
 */
final readonly class StubReleasesDriver implements ReleasesCapability
{
    public function __construct(private ?string $tag) {}

    public function tags(BindingContext $context, ?string $cursor = null): Page
    {
        return new Page($this->tag === null ? [] : [['tag' => $this->tag]]);
    }

    public function firstTagContaining(BindingContext $context, string $commitSha): ?string
    {
        return $this->tag;
    }
}
