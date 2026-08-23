<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * Whether a {@see \Modules\SAO\Models\ChangeRef} claims to *resolve* a ticket or
 * merely *mentions* it.
 *
 * Only `Fixes` counts as resolution evidence — `FixStatusResolver`,
 * `TimeToTruthService` and closure ignore mentions, which stay as timeline
 * context. A closing verb before a ticket key (`fixes SAO-1`) yields `Fixes`; a
 * bare reference (`see SAO-1`) yields `Mentions`. An upsert may upgrade a mention
 * to a fix, never the reverse.
 */
enum ChangeRefRelation: string
{
    /** The change claims to resolve the ticket — counts as evidence. */
    case Fixes = 'fixes';

    /** The change references the ticket in passing — context only. */
    case Mentions = 'mentions';

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
