<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use RuntimeException;

final class MissingCredentialException extends RuntimeException
{
    public static function forRef(string $ref): self
    {
        return new self("The credential reference [{$ref}] resolves to nothing.");
    }

    public static function forConnection(string $name): self
    {
        return new self("Connection [{$name}] has neither a credential_ref nor a stored credential.");
    }
}
