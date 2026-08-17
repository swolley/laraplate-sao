<?php

declare(strict_types=1);

namespace Modules\SAO\Console;

use Illuminate\Console\Command;
use Modules\SAO\Models\Connection;
use Modules\SAO\Services\ConnectionHealthService;

/**
 * Probes one or all SAO connections against their external systems and records
 * the result. The only non-test path that performs a live health check.
 */
final class CheckConnectionsHealthCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sao:connection:health {name? : The connection name to check; omit to check them all}';

    /**
     * @var string
     */
    protected $description = 'Probe SAO connections and record their health state';

    #[Override]
    public function handle(ConnectionHealthService $service): int
    {
        $query = Connection::query();

        $name = $this->argument('name');

        if (is_string($name) && $name !== '') {
            $query->where('name', $name);
        }

        $connections = $query->get();

        if ($connections->isEmpty()) {
            $this->warn(is_string($name) && $name !== ''
                ? "No connection named [{$name}]."
                : 'No connections configured.');

            return self::FAILURE;
        }

        $rows = [];
        $allHealthy = true;

        foreach ($connections as $connection) {
            $result = $service->check($connection);
            $allHealthy = $allHealthy && $result->healthy;

            $rows[] = [
                $connection->name,
                $connection->driver_key,
                $result->healthy ? 'healthy' : 'unhealthy',
                $result->detail ?? '',
            ];
        }

        $this->table(['Connection', 'Driver', 'Health', 'Detail'], $rows);

        return $allHealthy ? self::SUCCESS : self::FAILURE;
    }
}
