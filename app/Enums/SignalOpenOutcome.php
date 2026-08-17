<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The result of one attempt to open a ticket from a signal. Every reason the
 * open stops is explicit — nothing is silently defaulted.
 */
enum SignalOpenOutcome: string
{
    case Opened = 'opened';
    case AlreadyLinked = 'already_linked';
    case ProjectUnavailable = 'project_unavailable';
    case NoDefaultType = 'no_default_type';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
