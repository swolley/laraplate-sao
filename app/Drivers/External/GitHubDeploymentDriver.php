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
 * The `deploy` driver for GitHub's `deployment_status` webhook. Deliveries are
 * authenticated by GitHub's HMAC-SHA256 signature (`X-Hub-Signature-256:
 * sha256=<hex>`) over the raw body, compared to the connection's webhook secret.
 * Every status of one GitHub deployment shares the deployment id, so re-deliveries
 * advance the same {@see \Modules\SAO\Models\Deployment} rather than duplicating it.
 */
final readonly class GitHubDeploymentDriver implements DeployCapability, DriverInterface
{
    private const string SIGNATURE_HEADER = 'x-hub-signature-256';

    #[Override]
    public function key(): string
    {
        return 'github-deployment';
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
            new ConfigurationField('secret', 'string', 'The GitHub webhook secret used to sign deliveries', required: true, secret: true),
        ]);
    }

    #[Override]
    public function healthCheck(ConnectionContext $context): HealthCheckResult
    {
        $secret = (string) ($context->credentials['secret'] ?? '');

        return $secret === ''
            ? HealthCheckResult::unhealthy('No webhook secret configured for the GitHub deployment connection.')
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

        return hash_equals('sha256=' . hash_hmac('sha256', $payload, $secret), $provided);
    }

    /**
     * @return list<DeployEvent>
     */
    #[Override]
    public function unpack(BindingContext $context, string $payload): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($payload, true) ?? [];

        /** @var array<string, mixed> $deployment */
        $deployment = is_array($decoded['deployment'] ?? null) ? $decoded['deployment'] : [];
        /** @var array<string, mixed> $deploymentStatus */
        $deploymentStatus = is_array($decoded['deployment_status'] ?? null) ? $decoded['deployment_status'] : [];

        $version = $this->stringOrNull($deployment['ref'] ?? null)
            ?? $this->stringOrNull($deployment['sha'] ?? null);

        if ($version === null) {
            return [];
        }

        $status = $this->mapState($this->stringOrNull($deploymentStatus['state'] ?? null));
        $environment = $this->stringOrNull($deploymentStatus['environment'] ?? $deployment['environment'] ?? null);
        $externalId = $this->stringOrNull($deployment['id'] ?? null);

        return [new DeployEvent(
            version: $version,
            status: $status,
            environmentName: $environment,
            externalId: $externalId,
            startedAt: null,
            finishedAt: null,
            meta: array_filter([
                'sha' => $this->stringOrNull($deployment['sha'] ?? null),
                'ref' => $this->stringOrNull($deployment['ref'] ?? null),
                'state' => $this->stringOrNull($deploymentStatus['state'] ?? null),
            ], static fn (?string $value): bool => $value !== null),
        )];
    }

    private function mapState(?string $state): DeploymentStatus
    {
        return match ($state) {
            'success' => DeploymentStatus::Succeeded,
            'failure', 'error' => DeploymentStatus::Failed,
            'inactive' => DeploymentStatus::Superseded,
            default => DeploymentStatus::Started,
        };
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function signatureHeader(array $headers): ?string
    {
        foreach ($headers as $name => $value) {
            if (mb_strtolower($name) === self::SIGNATURE_HEADER) {
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
