<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Support;

/**
 * One field of a driver's configuration schema.
 *
 * The `secret` flag is load-bearing: secret fields are captured write-only and
 * resolved from storage/environment, never rendered back to a UI (spec §5).
 */
final readonly class ConfigurationField
{
    public function __construct(
        public string $name,
        public string $type,
        public string $label,
        public bool $required = false,
        public bool $secret = false,
    ) {}
}
