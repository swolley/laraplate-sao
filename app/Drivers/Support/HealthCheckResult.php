<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Support;

/**
 * The outcome of a driver's health check against a connection.
 */
final readonly class HealthCheckResult
{
    public function __construct(
        public bool $healthy,
        public ?string $detail = null,
    ) {}

    public static function healthy(?string $detail = null): self
    {
        return new self(true, $detail);
    }

    public static function unhealthy(string $detail): self
    {
        return new self(false, $detail);
    }
}
