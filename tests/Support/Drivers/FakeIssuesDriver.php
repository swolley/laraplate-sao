<?php

declare(strict_types=1);

namespace Modules\SAO\Tests\Support\Drivers;

use Modules\SAO\Drivers\Contracts\DriverInterface;
use Modules\SAO\Drivers\Contracts\IssuesCapability;
use Modules\SAO\Drivers\Support\ConfigurationField;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Drivers\Support\DriverConfigurationSchema;
use Modules\SAO\Drivers\Support\HealthCheckResult;
use Modules\SAO\Drivers\Support\Page;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Override;

/**
 * Minimal in-test driver used to exercise the driver contract shape. It performs
 * no I/O and exists only to prove a driver can satisfy DriverInterface plus one
 * capability.
 */
final class FakeIssuesDriver implements DriverInterface, IssuesCapability
{
    #[Override]
    public function key(): string
    {
        return 'fake';
    }

    /**
     * @return list<Capability>
     */
    #[Override]
    public function capabilities(): array
    {
        return [Capability::Issues];
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
            new ConfigurationField('token', 'string', 'API token', required: true, secret: true),
            new ConfigurationField('project', 'string', 'Project key', required: true, secret: false),
        ]);
    }

    #[Override]
    public function healthCheck(ConnectionContext $context): HealthCheckResult
    {
        return HealthCheckResult::healthy();
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Override]
    public function lookup(ConnectionContext $context, string $remoteId): ?array
    {
        return ['id' => $remoteId, 'title' => 'Fake issue'];
    }

    #[Override]
    public function list(ConnectionContext $context, ?string $cursor = null): Page
    {
        return new Page([['id' => '1'], ['id' => '2']], nextCursor: null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function create(ConnectionContext $context, array $attributes): array
    {
        return ['id' => '3'] + $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function update(ConnectionContext $context, string $remoteId, array $attributes): array
    {
        return ['id' => $remoteId] + $attributes;
    }

    #[Override]
    public function comment(ConnectionContext $context, string $remoteId, string $body): void {}

    /**
     * @param  array<string, string>  $statusMap
     */
    #[Override]
    public function translateStatus(array $statusMap, string $remoteStatus): ?string
    {
        return $statusMap[$remoteStatus] ?? null;
    }
}
