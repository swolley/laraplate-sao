<?php

declare(strict_types=1);

namespace Modules\SAO\Attribution;

use Modules\SAO\Enums\ChangeRefRelation;

/**
 * A single ticket key found in a piece of code text, with how it was referenced.
 * A closing verb before the key ("fixes SAO-1") makes it a {@see ChangeRefRelation::Fixes};
 * a bare reference ("see SAO-1") a {@see ChangeRefRelation::Mentions}.
 */
final readonly class TicketReference
{
    public function __construct(
        public string $key,
        public ChangeRefRelation $relation,
    ) {}
}
