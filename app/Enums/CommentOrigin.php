<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * Distinguishes a human comment from one written by automation.
 *
 * A dedicated bot user was rejected: a user can be assigned, filtered on and
 * impersonated, none of which is true of an origin flag.
 */
enum CommentOrigin: string
{
    case Human = 'human';
    case System = 'system';

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
