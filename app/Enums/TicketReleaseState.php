<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * How a ticket is attributed to a release. `affected` marks the release where
 * the ticket's problem was detected (any maturity, including an uncurated
 * `observed` version); `promised` means the fix is claimed for the release (it
 * is announced, or only a candidate tag carries it); `shipped` means a stable
 * tag containing the fix exists. Resolution states (`promised`/`shipped`) only
 * attach to stable-eligible releases; `affected` accepts any. The state is
 * deliberately independent of the ticket's own workflow status.
 */
enum TicketReleaseState: string
{
    case Affected = 'affected';
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
