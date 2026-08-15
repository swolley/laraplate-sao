<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * How a driver receives events (spec D6: ingest is push-first).
 *
 * A driver declares the modes it supports so polling is implemented only where a
 * real driver needs it, without redesign.
 */
enum IngestMode: string
{
    case Push = 'push';
    case Pull = 'pull';
    case InProcess = 'in_process';

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
