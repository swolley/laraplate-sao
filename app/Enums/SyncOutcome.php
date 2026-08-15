<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The result of a single issue synchronization attempt. Every reason a sync
 * stops is explicit — nothing is silently defaulted.
 */
enum SyncOutcome: string
{
    case Created = 'created';
    case Updated = 'updated';
    case SkippedIdempotent = 'skipped_idempotent';
    case SkippedDirection = 'skipped_direction';
    case UnmappedStatus = 'unmapped_status';
    case NotFound = 'not_found';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
