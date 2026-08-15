<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The kind of link between two tickets. Directional types read differently from
 * each end (A "blocks" B means B is "blocked by" A); symmetric types read the
 * same from both ends.
 */
enum TicketRelationType: string
{
    case Blocks = 'blocks';
    case Duplicates = 'duplicates';
    case Relates = 'relates';

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
     * Whether the relation reads differently depending on which ticket you view
     * it from. `relates` is symmetric; the rest are directional.
     */
    public function isDirectional(): bool
    {
        return $this !== self::Relates;
    }
}
