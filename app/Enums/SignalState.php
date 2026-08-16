<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The lifecycle state of a {@see \Modules\SAO\Models\Signal}. Distinct from a
 * ticket's workflow status: a signal is machine-managed grouping, opened on the
 * first occurrence and resolved when the error stops (or muted deliberately).
 */
enum SignalState: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Muted = 'muted';
    case Archived = 'archived';

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
