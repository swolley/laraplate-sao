<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The last known reachability of a connection's external system.
 */
enum ConnectionHealth: string
{
    case Unknown = 'unknown';
    case Healthy = 'healthy';
    case Unhealthy = 'unhealthy';

    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
