<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use RuntimeException;

/**
 * A system comment records what automation observed. Letting a person rewrite it
 * would make the ticket history untrustworthy precisely where it is most relied
 * upon.
 */
final class ImmutableSystemCommentException extends RuntimeException
{
    public static function make(): self
    {
        return new self('A system comment cannot be modified.');
    }
}
