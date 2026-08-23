<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\External;

use Modules\SAO\Drivers\Contracts\DeployCapability;
use Modules\SAO\Drivers\Contracts\DriverInterface;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConfigurationField;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Drivers\Support\DeployEvent;
use Modules\SAO\Drivers\Support\DriverConfigurationSchema;
use Modules\SAO\Drivers\Support\HealthCheckResult;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\DeploymentStatus;
use Modules\SAO\Enums\IngestMode;
use Override;

/**
 * A generic `deploy` driver for any CD system that can POST a JSON deploy
 * notification: a token-authenticated webhook. The delivery carries a shared
 * token in a header, compared to the connection secret. The body is either a
 * single deploy object or a list under `deployments`, each mapped to a
 * {@see DeployEvent}. It is the integration-free push counterpart to the
 * `sao:deploy:record` command.
 */
final readonly class WebhookDeployDriver implements DeployCapability, DriverInterface
{
    private const string TOKEN_HEADER = 'x-deploy-token';

    #[Override]
    public function key(): string
    {
        return 'webhook-deploy';
    }

    /**
     * @return list<Capability>
     */
    #[Override]
    public function capabilities(): array
    {
        return [Capability::Deploy];
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
            new ConfigurationField('secret', 'string', 'Shared token the CD system sends in the X-Deploy-Token header', required: true, secret: true),
        ]);
    }

    #[Override]
    public function healthCheck(ConnectionContext $context): HealthCheckResult
    {
        $secret = (string) ($context->credentials['secret'] ?? '');

        return $secret === ''
            ? HealthCheckResult::unhealthy('No shared token configured for the deploy webhook connection.')
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

        $provided = $this->tokenHeader($headers);

        return $provided !== null && hash_equals($secret, $provided);
    }

    /**
     * @return list<DeployEvent>
     */
    #[Override]
    public function unpack(BindingContext $context, string $payload): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($payload, true) ?? [];

        /** @var list<array<string, mixed>> $rows */
        $rows = is_array($decoded['deployments'] ?? null)
            ? array_values(array_filter($decoded['deployments'], 'is_array'))
            : [$decoded];

        $events = [];

        foreach ($rows as $row) {
            $version = $this->stringOrNull($row['version'] ?? null);

            if ($version === null) {
                continue;
            }

            $status = DeploymentStatus::tryFrom((string) ($row['status'] ?? '')) ?? DeploymentStatus::Succeeded;

            $events[] = new DeployEvent(
                version: $version,
                status: $status,
                environmentName: $this->stringOrNull($row['environment'] ?? null),
                externalId: $this->stringOrNull($row['external_id'] ?? $row['id'] ?? null),
                startedAt: null,
                finishedAt: null,
                meta: is_array($row['meta'] ?? null) ? $row['meta'] : [],
            );
        }

        return $events;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function tokenHeader(array $headers): ?string
    {
        foreach ($headers as $name => $value) {
            if (mb_strtolower($name) === self::TOKEN_HEADER) {
                return $value;
            }
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
