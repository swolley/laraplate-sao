<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * Where a {@see \Modules\SAO\Models\Release} sits in its curation lifecycle.
 * `observed` is a version seen in the wild (extracted from a log at ingest, or
 * entered by whoever opened a ticket) that nobody has curated yet — it is hidden
 * from version lists by default and, having no stable tag, can never be a
 * resolution target. `announced` is a version being assembled (tickets can be
 * promised to it); `shipped` once a stable tag realizing it exists. Status is the
 * curation lifecycle; the maturity lives on the tags (see {@see ReleaseTagKind}).
 */
enum ReleaseStatus: string
{
    case Observed = 'observed';
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
