<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use InvalidArgumentException;

/**
 * A ticket relating to itself carries no meaning and would let a ticket block or
 * duplicate itself, so it is rejected at creation.
 */
final class SelfTicketRelationException extends InvalidArgumentException
{
    public static function make(): self
    {
        return new self('A ticket cannot be related to itself.');
    }
}
