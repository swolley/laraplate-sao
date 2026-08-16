<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Contracts;

use Modules\SAO\Drivers\Support\BindingContext;

/**
 * Read line ownership for a file at a ref, aggregated per author.
 *
 * An optional extension of `vcs`: line blame is not uniformly exposed over REST
 * (GitHub offers it through GraphQL; GitLab and Bitbucket do not), so a driver
 * implements this only where the host supports it. Consumers depend on this
 * contract, not on a concrete driver, and simply skip a connection whose driver
 * does not implement it.
 */
interface BlameCapability
{
    /**
     * The per-author line tally for a file at a ref.
     *
     * @return list<array{author: ?string, author_email: ?string, lines: int}>
     */
    public function blame(BindingContext $context, string $path, string $ref): array;
}
