<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * Fixed rather than configurable: priority needs no transitions and no canonical
 * meaning separate from its name, so it is already canonical.
 */
enum TicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

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
