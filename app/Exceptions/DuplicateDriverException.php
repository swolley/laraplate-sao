<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use RuntimeException;

final class DuplicateDriverException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("A SAO driver is already registered for key [{$key}].");
    }
}
