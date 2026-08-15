<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use RuntimeException;

final class UnknownDriverException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No SAO driver is registered for key [{$key}].");
    }
}
