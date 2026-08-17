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
 * An issues driver that returns one issue per page, so a poller test can prove
 * the list loop follows `nextCursor` to the end instead of reading only the
 * first page. Each page carries a distinct remote id; `pageReads` counts how
 * many times the driver was asked for a page.
 */
final class PaginatingIssuesDriver implements DriverInterface, IssuesCapability
{
    public int $pageReads = 0;

    public function __construct(private readonly int $total = 3) {}

    #[Override]
    public function key(): string
    {
        return 'paginating';
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
        return (new NormalizedIssue(remoteId: $remoteId, title: "Remote {$remoteId}"))->toArray();
    }

    #[Override]
    public function list(BindingContext $context, ?string $cursor = null): Page
    {
        $this->pageReads++;

        $index = $cursor === null ? 0 : (int) $cursor;
        $number = $index + 1;

        $item = (new NormalizedIssue(remoteId: "r-{$number}", title: "Issue {$number}"))->toArray();

        return new Page([$item], nextCursor: $number < $this->total ? (string) $number : null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function create(BindingContext $context, array $attributes): array
    {
        return (new NormalizedIssue(remoteId: 'new', title: $attributes['title'] ?? ''))->toArray();
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
