<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The nature of a {@see \Modules\SAO\Models\ReleaseTag}. Maturity is a property
 * of the tag, not of the release: one product version passes through `alpha`,
 * `beta`, `candidate` (an RC keeps a testable reference for staging) and finally
 * `stable`, the only shippable reference. A release's effective maturity is the
 * highest {@see self::precedence()} among its tags.
 */
enum ReleaseTagKind: string
{
    case Alpha = 'alpha';
    case Beta = 'beta';
    case Candidate = 'candidate';
    case Stable = 'stable';

    /**
     * Ordering used to derive a release's effective maturity: higher wins.
     */
    public function precedence(): int
    {
        return match ($this) {
            self::Alpha => 0,
            self::Beta => 1,
            self::Candidate => 2,
            self::Stable => 3,
        };
    }

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
