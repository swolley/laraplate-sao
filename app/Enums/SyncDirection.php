<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The direction in which a binding synchronizes a ticket with its remote
 * counterpart (D5). Configured per binding.
 */
enum SyncDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Bidirectional = 'bidirectional';
    case Disabled = 'disabled';

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

    public function syncsInbound(): bool
    {
        return in_array($this, [self::Inbound, self::Bidirectional], true);
    }

    public function syncsOutbound(): bool
    {
        return in_array($this, [self::Outbound, self::Bidirectional], true);
    }
}
