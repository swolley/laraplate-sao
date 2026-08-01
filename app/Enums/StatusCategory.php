<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The canonical meaning behind a configurable status.
 *
 * Phase 3 maps external tracker statuses onto these categories rather than onto
 * status names, and phase 6 decides closures by reading them.
 */
enum StatusCategory: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Rejected = 'rejected';

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

    /**
     * Whether the ticket needs no further work.
     *
     * Resolved is deliberately not terminal: it means fixed but unconfirmed,
     * which is the state phase 6 observes before closing anything.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Rejected], true);
    }
}
