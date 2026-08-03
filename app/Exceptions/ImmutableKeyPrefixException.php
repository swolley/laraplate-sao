<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use RuntimeException;

/**
 * Ticket keys end up in commit messages, which phase 5 parses. A prefix that
 * changes makes already-written history unreadable.
 */
final class ImmutableKeyPrefixException extends RuntimeException
{
    public static function forProject(string $name): self
    {
        return new self(
            "The key prefix of project \"{$name}\" cannot change: ticket numbers have already been allocated.",
        );
    }
}
