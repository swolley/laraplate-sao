<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The nature of a {@see \Modules\SAO\Models\ReleaseTag}. A `stable` tag is the
 * shippable reference; a `candidate` (an RC) keeps a testable reference for
 * staging without claiming the release is delivered.
 */
enum ReleaseTagKind: string
{
    case Stable = 'stable';
    case Candidate = 'candidate';

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
