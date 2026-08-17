<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use Modules\SAO\Models\OwnershipSuggestion;
use RuntimeException;

final class OwnershipSuggestionNotApplicableException extends RuntimeException
{
    public static function noSuggestedUser(OwnershipSuggestion $suggestion): self
    {
        return new self("Ownership suggestion [{$suggestion->getKey()}] has no suggested user to assign.");
    }

    public static function noTicket(OwnershipSuggestion $suggestion): self
    {
        return new self("Ownership suggestion [{$suggestion->getKey()}] has no ticket to assign.");
    }
}
