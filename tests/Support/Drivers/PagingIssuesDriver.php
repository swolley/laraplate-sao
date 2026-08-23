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
 * An in-memory issues driver that paginates its seed by an offset carried in the
 * cursor (like Redmine's `offset`/`limit`). It records the cursor of every
 * {@see list()} call so a resume test can prove the importer restarts from the
 * stored cursor instead of walking from page one.
 */
final class PagingIssuesDriver implements DriverInterface, IssuesCapability
{
    /**
     * @var list<string|null>
     */
    public array $listedCursors = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $issues = [];

    /**
     * @param  array<string, string>  $seed  remoteId => remoteStatus, in list order
     */
    public function __construct(array $seed, private readonly int $pageSize = 2)
    {
        foreach ($seed as $remoteId => $status) {
            $this->issues[] = ['remote_id' => (string) $remoteId, 'title' => "Remote {$remoteId}", 'remote_status' => $status];
        }
    }

    #[Override]
    public function key(): string
    {
        return 'paging';
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
        return [IngestMode::Pull];
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
        foreach ($this->issues as $issue) {
            if ($issue['remote_id'] === $remoteId) {
                return $issue;
            }
        }

        return null;
    }

    #[Override]
    public function list(BindingContext $context, ?string $cursor = null): Page
    {
        $this->listedCursors[] = $cursor;

        $offset = $cursor === null ? 0 : (int) $cursor;
        $items = array_slice($this->issues, $offset, $this->pageSize);
        $next = $offset + $this->pageSize;

        return new Page(array_values($items), nextCursor: $next < count($this->issues) ? (string) $next : null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function create(BindingContext $context, array $attributes): array
    {
        return (new NormalizedIssue(remoteId: (string) (count($this->issues) + 1), title: $attributes['title'] ?? ''))->toArray();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function update(BindingContext $context, string $remoteId, array $attributes): array
    {
        return (new NormalizedIssue(remoteId: $remoteId, title: $attributes['title'] ?? ''))->toArray();
    }

    #[Override]
    public function comment(BindingContext $context, string $remoteId, string $body): void {}

    /**
     * @param  array<string, string>  $statusMap
     */
    #[Override]
    public function translateStatus(array $statusMap, string $remoteStatus): ?string
    {
        return $statusMap[$remoteStatus] ?? null;
    }
}
