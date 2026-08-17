<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Support;

use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;

/**
 * Shared plumbing for `logs` drivers: the fixed capability/ingest declarations,
 * secret-based health, header lookup, HMAC/token verification, and payload
 * decoding. Each driver keeps its own `unpack()` and `carriesNativeGroupKey()`
 * — the parts that actually differ between a Sentry-style error tracker and a
 * Graylog-style aggregator.
 */
trait LogsDriverBoilerplate
{
    /**
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return [Capability::Logs];
    }

    /**
     * @return list<IngestMode>
     */
    public function ingestModes(): array
    {
        return [IngestMode::Push];
    }

    public function healthCheck(ConnectionContext $context): HealthCheckResult
    {
        $secret = (string) ($context->credentials['secret'] ?? '');

        return $secret === ''
            ? HealthCheckResult::unhealthy('No shared secret configured for the ' . $this->key() . ' connection.')
            : HealthCheckResult::healthy();
    }

    /**
     * The connection's shared secret, from the resolved binding.
     */
    protected function secret(BindingContext $context): string
    {
        return (string) ($context->connection->credentials['secret'] ?? '');
    }

    /**
     * Verify an HMAC-SHA256 of the raw body under the connection secret against
     * a signature header.
     *
     * @param  array<string, string>  $headers
     */
    protected function hmacVerified(BindingContext $context, string $payload, array $headers, string $signatureHeader): bool
    {
        $secret = $this->secret($context);
        $provided = $this->headerValue($headers, $signatureHeader);

        return $secret !== '' && $provided !== null && hash_equals(hash_hmac('sha256', $payload, $secret), $provided);
    }

    /**
     * Verify a shared token sent verbatim in a header against the connection
     * secret.
     *
     * @param  array<string, string>  $headers
     */
    protected function tokenVerified(BindingContext $context, array $headers, string $tokenHeader): bool
    {
        $secret = $this->secret($context);
        $provided = $this->headerValue($headers, $tokenHeader);

        return $secret !== '' && $provided !== null && hash_equals($secret, $provided);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (mb_strtolower($key) === mb_strtolower($name)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(string $payload): array
    {
        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
