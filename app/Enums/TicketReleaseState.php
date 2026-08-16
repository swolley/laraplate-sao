<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * How a ticket is attributed to a release. `promised` means the fix is claimed
 * for the release (it is announced, or only a candidate tag carries it);
 * `shipped` means a stable tag containing the fix exists. The state is
 * deliberately independent of the ticket's own workflow status.
 */
enum TicketReleaseState: string
{
    case Promised = 'promised';
    case Shipped = 'shipped';

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
