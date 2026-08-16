<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use RuntimeException;

final class UnknownClosureConditionException extends RuntimeException
{
    public static function for(string $key): self
    {
        return new self("Unknown closure condition [{$key}].");
    }
}
