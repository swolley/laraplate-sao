<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use RuntimeException;

/**
 * A scheme with two creation transitions cannot answer "where does a new ticket
 * start". The composite unique index cannot express this, because SQL treats
 * rows with a null from_status_id as distinct.
 */
final class DuplicateCreationTransitionException extends RuntimeException
{
    public static function make(): self
    {
        return new self('A workflow scheme may declare only one creation transition.');
    }
}
