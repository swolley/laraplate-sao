<?php

declare(strict_types=1);

namespace Modules\SAO\Tests\Support\Drivers;

use Modules\SAO\Drivers\Contracts\DriverInterface;
use Modules\SAO\Drivers\Contracts\IssuesCapability;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Drivers\Support\DriverConfigurationSchema;
use Modules\SAO\Drivers\Support\HealthCheckResult;
use Modules\SAO\Drivers\Support\NormalizedIssue;
use Modules\SAO\Drivers\Support\Page;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Override;

/**
 * An in-memory issues driver that records how many times each write was called,
 * so sync tests can prove idempotency (a repeated push creates nothing new). It
 * stands in for an external tracker without any network.
 */
final class RecordingIssuesDriver implements DriverInterface, IssuesCapability
{
    public int $createCount = 0;

    public int $updateCount = 0;

    public int $commentCount = 0;

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $remote = [];

    private int $sequence = 0;

    /**
     * @param  array<string, string>  $seed  Optional remoteId => remoteStatus fixture for pull tests.
     */
    public function __construct(array $seed = [])
    {
        foreach ($seed as $remoteId => $status) {
            $this->remote[$remoteId] = ['remote_id' => $remoteId, 'title' => "Remote {$remoteId}", 'remote_status' => $status];
        }
    }

    #[Override]
    public function key(): string
    {
        return 'recording';
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
        return new DriverConfigurationSchema([]);
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
    public function lookup(BindingContext $context, string $remoteId): ?array
    {
        return $this->remote[$remoteId] ?? null;
    }

    #[Override]
    public function list(BindingContext $context, ?string $cursor = null): Page
    {
        return new Page(array_values($this->remote), nextCursor: null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function create(BindingContext $context, array $attributes): array
    {
        $this->createCount++;
        $this->sequence++;
        $id = (string) $this->sequence;
        $issue = (new NormalizedIssue(remoteId: $id, title: $attributes['title'] ?? ''))->toArray();
        $this->remote[$id] = $issue;

        return $issue;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function update(BindingContext $context, string $remoteId, array $attributes): array
    {
        $this->updateCount++;
        $issue = (new NormalizedIssue(remoteId: $remoteId, title: $attributes['title'] ?? ''))->toArray();
        $this->remote[$remoteId] = $issue;

        return $issue;
    }

    #[Override]
    public function comment(BindingContext $context, string $remoteId, string $body): void
    {
        $this->commentCount++;
    }

    /**
     * @param  array<string, string>  $statusMap
     */
    #[Override]
    public function translateStatus(array $statusMap, string $remoteStatus): ?string
    {
        return $statusMap[$remoteStatus] ?? null;
    }
}
