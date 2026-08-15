<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers;

use Modules\SAO\Drivers\Contracts\DriverInterface;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Exceptions\DuplicateDriverException;
use Modules\SAO\Exceptions\UnknownDriverException;

/**
 * The open registry of SAO drivers.
 *
 * Populated by SAO's service provider but open by design: any module or
 * third-party package registers a driver without editing SAO (spec §5).
 * Registered as a singleton so contributions from several providers accumulate.
 * Duplicate keys throw rather than silently overwrite, so a collision surfaces
 * at boot rather than as a mystery at run time.
 */
final class DriverRegistry
{
    /**
     * @var array<string, DriverInterface>
     */
    private array $drivers = [];

    public function register(DriverInterface $driver): void
    {
        $key = $driver->key();

        if (isset($this->drivers[$key])) {
            throw DuplicateDriverException::forKey($key);
        }

        $this->drivers[$key] = $driver;
    }

    public function has(string $key): bool
    {
        return isset($this->drivers[$key]);
    }

    public function get(string $key): DriverInterface
    {
        return $this->drivers[$key] ?? throw UnknownDriverException::forKey($key);
    }

    /**
     * @return list<DriverInterface>
     */
    public function all(): array
    {
        return array_values($this->drivers);
    }

    /**
     * @return list<DriverInterface>
     */
    public function withCapability(Capability $capability): array
    {
        return array_values(array_filter(
            $this->drivers,
            static fn (DriverInterface $driver): bool => in_array($capability, $driver->capabilities(), true),
        ));
    }
}
