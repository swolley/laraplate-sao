<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * How much of an external tracker's history a migration import pulls in.
 *
 * `All` imports every issue; `Open` skips issues whose remote status maps (through
 * the binding's `status_map`) to a terminal canonical category (closed/rejected).
 * An unmapped remote status is treated as open, so nothing still active is lost.
 */
enum ImportScope: string
{
    case All = 'all';
    case Open = 'open';

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
