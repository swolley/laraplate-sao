<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Support;

use Modules\SAO\Exceptions\MissingCredentialException;
use Modules\SAO\Models\Connection;

/**
 * The single sanctioned path from a connection to its secret (F4).
 *
 * Resolution order: a set `credential_ref` reads from config/environment and
 * wins; otherwise the decrypted `credential` column is used. The secret is
 * returned for in-memory use only — never written back to the connection and
 * never logged.
 */
final class ConnectionCredentialResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(Connection $connection): array
    {
        $ref = $connection->credential_ref;

        if ($ref !== null && $ref !== '') {
            $value = config($ref);

            if ($value === null) {
                throw MissingCredentialException::forRef($ref);
            }

            return is_array($value) ? $value : ['value' => $value];
        }

        $credential = $connection->getAttribute('credential');

        if (is_array($credential) && $credential !== []) {
            return $credential;
        }

        throw MissingCredentialException::forConnection((string) $connection->name);
    }
}
