<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The deterministic rule that produced an ownership suggestion. Ordered by
 * strength: an explicit CODEOWNERS entry outranks blame concentration, which
 * outranks a recent touch, which outranks a plain path-prefix owner. AI may
 * later phrase the suggestion text; it never invents ownership (D14).
 */
enum OwnershipRule: string
{
    case Codeowners = 'codeowners';
    case BlameConcentration = 'blame_concentration';
    case RecentTouch = 'recent_touch';
    case PathOwner = 'path_owner';

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
     * A short human label for the rule, for suggestion text and UI badges.
     */
    public function label(): string
    {
        return match ($this) {
            self::Codeowners => 'CODEOWNERS entry',
            self::BlameConcentration => 'blame concentration',
            self::RecentTouch => 'recent commits',
            self::PathOwner => 'path ownership',
        };
    }

    /**
     * Lower wins. The precedence is what makes the choice between two candidates
     * deterministic before their scores are even compared.
     */
    public function precedence(): int
    {
        return match ($this) {
            self::Codeowners => 1,
            self::BlameConcentration => 2,
            self::RecentTouch => 3,
            self::PathOwner => 4,
        };
    }
}
