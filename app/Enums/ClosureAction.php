<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * What a {@see \Modules\SAO\Models\ClosurePolicy} does when its conditions all
 * hold. `Close` acts, `Propose` suggests without acting, `NotifyOnly` just
 * signals. The prudent default on a `shadow` external binding — where the
 * foreign tracker owns the ticket — is `Propose`, never `Close`.
 */
enum ClosureAction: string
{
    case Close = 'close';
    case Propose = 'propose';
    case NotifyOnly = 'notify_only';

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
