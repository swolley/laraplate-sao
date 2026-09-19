<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use InvalidArgumentException;

/**
 * A ticket resolution may only reference a stable release. Attributing a ticket
 * as `shipped` to a release with no stable tag would claim a fix ships in a
 * version that has never been stably released, so it is rejected. Being
 * `affected` by any maturity stays allowed.
 */
final class UnstableResolutionReleaseException extends InvalidArgumentException
{
    public static function make(int $releaseId): self
    {
        return new self("Release {$releaseId} has no stable tag and cannot be a resolution target.");
    }
}
