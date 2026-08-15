<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use Modules\SAO\Enums\Capability;
use RuntimeException;

final class UnsupportedCapabilityException extends RuntimeException
{
    public static function for(string $driverKey, Capability $capability): self
    {
        return new self("Driver [{$driverKey}] does not expose the [{$capability->value}] capability.");
    }
}
