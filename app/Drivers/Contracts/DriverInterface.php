<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Contracts;

use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Drivers\Support\DriverConfigurationSchema;
use Modules\SAO\Drivers\Support\HealthCheckResult;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;

/**
 * The base contract every SAO driver implements.
 *
 * A driver is registered code; a connection is a configured instance of it. A
 * driver additionally implements one or more capability contracts
 * ({@see IssuesCapability}, {@see VcsCapability}, {@see LogsCapability},
 * {@see ReleasesCapability}); domain services depend on those, never on a
 * concrete driver.
 */
interface DriverInterface
{
    public function key(): string;

    /**
     * @return list<Capability>
     */
    public function capabilities(): array;

    /**
     * @return list<IngestMode>
     */
    public function ingestModes(): array;

    public function configurationSchema(): DriverConfigurationSchema;

    public function healthCheck(ConnectionContext $context): HealthCheckResult;
}
