<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\External;

use Modules\SAO\Drivers\Contracts\DriverInterface;
use Modules\SAO\Drivers\Contracts\LogsCapability;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConfigurationField;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Drivers\Support\DriverConfigurationSchema;
use Modules\SAO\Drivers\Support\HealthCheckResult;
use Modules\SAO\Drivers\Support\Page;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Override;

/**
 * The `logs` driver for Sentry, over its webhook (push) transport. Sentry is an
 * error tracker, so it arrives with a stable native issue id SAO must respect
 * rather than recompute — `carriesNativeGroupKey()` is true and each unpacked
 * event carries `native_key` + `source = sentry`, which the `GroupKeyResolver`
 * namespaces as `sentry:<id>`. Deliveries are signed with an HMAC-SHA256 of the
 * body under the connection's shared secret.
 */
final readonly class SentryDriver implements DriverInterface, LogsCapability
{
    #[Override]
    public function key(): string
    {
        return 'sentry';
    }

    /**
     * @return list<Capability>
     */
    #[Override]
    public function capabilities(): array
    {
        return [Capability::Logs];
    }

    /**
     * @return list<IngestMode>
     */
    #[Override]
    public function ingestModes(): array
    {
        return [IngestMode::Push];
    }

    #[Override]
    public function configurationSchema(): DriverConfigurationSchema
    {
        return new DriverConfigurationSchema([
            new ConfigurationField('secret', 'string', 'Sentry webhook signing secret', required: true, secret: true),
        ]);
    }

    #[Override]
    public function healthCheck(ConnectionContext $context): HealthCheckResult
    {
        $secret = (string) ($context->credentials['secret'] ?? '');

        return $secret === ''
            ? HealthCheckResult::unhealthy('No signing secret configured for the Sentry connection.')
            : HealthCheckResult::healthy();
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[Override]
    public function verifySignature(BindingContext $context, string $payload, array $headers): bool
    {
        $secret = (string) ($context->connection->credentials['secret'] ?? '');

        if ($secret === '') {
            return false;
        }

        $provided = $this->signatureHeader($headers);

        if ($provided === null) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $provided);
    }

    #[Override]
    public function unpack(BindingContext $context, string $payload): Page
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($payload, true) ?? [];

        /** @var array<string, mixed> $data */
        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];

        /** @var array<string, mixed> $issue */
        $issue = is_array($data['issue'] ?? null) ? $data['issue'] : [];
        /** @var array<string, mixed> $event */
        $event = is_array($data['event'] ?? null) ? $data['event'] : [];

        $nativeKey = (string) ($issue['id'] ?? $event['issue_id'] ?? '');

        if ($nativeKey === '') {
            return new Page([]);
        }

        return new Page([[
            'native_key' => $nativeKey,
            'source' => $this->key(),
            'message' => $this->message($issue, $event),
            'level' => $this->stringOrNull($issue['level'] ?? $event['level'] ?? null),
            'environment' => $this->stringOrNull($event['environment'] ?? null),
            'culprit' => $this->stringOrNull($issue['culprit'] ?? $event['culprit'] ?? null),
            'url' => $this->stringOrNull($issue['web_url'] ?? $issue['url'] ?? null),
            'occurred_at' => $this->stringOrNull($event['datetime'] ?? $issue['lastSeen'] ?? null),
            'raw' => $decoded,
        ]]);
    }

    #[Override]
    public function carriesNativeGroupKey(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $issue
     * @param  array<string, mixed>  $event
     */
    private function message(array $issue, array $event): string
    {
        return (string) ($issue['title'] ?? $event['title'] ?? $event['message'] ?? '');
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function signatureHeader(array $headers): ?string
    {
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'sentry-hook-signature') {
                return $value;
            }
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
