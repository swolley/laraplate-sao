<?php

declare(strict_types=1);

namespace Modules\SAO\Tests\Support\Drivers;

use Modules\SAO\Drivers\Contracts\DriverInterface;
use Modules\SAO\Drivers\Contracts\IssueHistoryCapability;
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
 * An in-memory issues driver that also serves comment and attachment history, so
 * the tracker importer's history path can be exercised without any network. It
 * lists its seeded issues in a single page and returns the fixed comments and
 * (already-downloaded) attachment bytes registered per remote id.
 */
final class HistoryIssuesDriver implements DriverInterface, IssueHistoryCapability, IssuesCapability
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $issues = [];

    /**
     * @param  array<string, string>  $seed  remoteId => remoteStatus
     * @param  array<string, list<array{remote_id: string, body: string}>>  $comments  remoteId => comments
     * @param  array<string, list<array{remote_id: string, filename: string, contents: string}>>  $attachments  remoteId => attachments
     */
    public function __construct(
        array $seed,
        private readonly array $comments = [],
        private readonly array $attachments = [],
    ) {
        foreach ($seed as $remoteId => $status) {
            $this->issues[] = ['remote_id' => (string) $remoteId, 'title' => "Remote {$remoteId}", 'remote_status' => $status];
        }
    }

    #[Override]
    public function key(): string
    {
        return 'history';
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
        return new Page(array_values($this->issues), nextCursor: null);
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

    /**
     * @return list<array{remote_id: string, body: string, author?: string|null}>
     */
    #[Override]
    public function comments(BindingContext $context, string $remoteId): array
    {
        return $this->comments[$remoteId] ?? [];
    }

    /**
     * @return list<array{remote_id: string, filename: string, contents: string}>
     */
    #[Override]
    public function attachments(BindingContext $context, string $remoteId): array
    {
        return $this->attachments[$remoteId] ?? [];
    }
}
