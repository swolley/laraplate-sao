<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The outcome of one received delivery. Every delivery ends in one of these,
 * recorded on the `IngestEvent`, so silence is auditable without app logs
 * (spec §13 phase 4).
 */
enum IngestStatus: string
{
    case Received = 'received';
    case Discarded = 'discarded';
    case Uncorrelated = 'uncorrelated';
    case Ingested = 'ingested';
    case Failed = 'failed';

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
