<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The kind of code artefact a {@see \Modules\SAO\Models\ChangeRef} ties to a
 * ticket.
 */
enum ChangeRefType: string
{
    case Commit = 'commit';
    case PullRequest = 'pull_request';
    case Tag = 'tag';

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
