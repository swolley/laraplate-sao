<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * Where a {@see \Modules\SAO\Models\Release} sits in its lifecycle. A release
 * is `announced` while it is being assembled (tickets can be promised to it)
 * and `shipped` once a stable tag realizing it exists.
 */
enum ReleaseStatus: string
{
    case Announced = 'announced';
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
