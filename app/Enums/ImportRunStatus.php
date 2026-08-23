<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The lifecycle of a tracker-import run — the persisted cursor that makes a large
 * migration resumable across invocations, crashes and requeues.
 *
 * A `Running` run holds the next page cursor and the running counts; the importer
 * resumes it instead of restarting from page one. It flips to `Completed` only
 * when the page walk is exhausted, so an interrupted import is always picked up
 * exactly where it stopped rather than re-walked from the beginning.
 */
enum ImportRunStatus: string
{
    /**
     * The page walk is in progress (or was interrupted) — resume from the cursor.
     */
    case Running = 'running';

    /**
     * The page walk reached the end — nothing left to resume.
     */
    case Completed = 'completed';

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
