<?php

declare(strict_types=1);

namespace Modules\SAO\Tests\Support\Drivers;

use Modules\SAO\Drivers\Contracts\DriverInterface;
use Modules\SAO\Drivers\Contracts\IssuesCapability;
use Modules\SAO\Drivers\Contracts\ReleasesCapability;
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
 * A network-free reference driver that implements `issues` and `releases` over
 * in-memory arrays. It exists only in test support and proves the whole stack —
 * registry, connection, credential resolver, capability calls — runs offline.
 *
 * It paginates with a deliberately small page size so the conformance suite's
 * multi-page fixture exercises cursor following rather than a first-page-only
 * read.
 */
final class InMemoryDriver implements DriverInterface, IssuesCapability, ReleasesCapability
{
    private const int PAGE_SIZE = 2;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $issues;

    /**
     * @var list<string>
     */
    private array $tags;

    public function __construct()
    {
        $this->issues = [
            '1' => ['remote_id' => '1', 'title' => 'First', 'status' => 'Done'],
            '2' => ['remote_id' => '2', 'title' => 'Second', 'status' => 'Open'],
            '3' => ['remote_id' => '3', 'title' => 'Third', 'status' => 'Open'],
            '4' => ['remote_id' => '4', 'title' => 'Fourth', 'status' => 'Open'],
            '5' => ['remote_id' => '5', 'title' => 'Fifth', 'status' => 'Open'],
        ];
        $this->tags = ['v1.0.0', 'v1.0.1', 'v1.1.0', 'v2.0.0'];
    }

    #[Override]
    public function key(): string
    {
        return 'in-memory';
    }

    /**
     * @return list<Capability>
     */
    #[Override]
    public function capabilities(): array
    {
        return [Capability::Issues, Capability::Releases];
    }

    /**
     * @return list<IngestMode>
     */
    #[Override]
    public function ingestModes(): array
    {
        return [IngestMode::InProcess];
    }

    #[Override]
    public function configurationSchema(): DriverConfigurationSchema
    {
        return new DriverConfigurationSchema([
            new ConfigurationField('token', 'string', 'API token', required: true, secret: true),
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
    public function lookup(BindingContext $context, string $remoteId): ?array
    {
        return $this->issues[$remoteId] ?? null;
    }

    #[Override]
    public function list(BindingContext $context, ?string $cursor = null): Page
    {
        return $this->paginate(array_values($this->issues), $cursor);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function create(BindingContext $context, array $attributes): array
    {
        $id = (string) (count($this->issues) + 1);
        $issue = ['remote_id' => $id] + $attributes;
        $this->issues[$id] = $issue;

        return $issue;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function update(BindingContext $context, string $remoteId, array $attributes): array
    {
        $this->issues[$remoteId] = array_merge($this->issues[$remoteId] ?? ['remote_id' => $remoteId], $attributes);

        return $this->issues[$remoteId];
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

    #[Override]
    public function tags(BindingContext $context, ?string $cursor = null): Page
    {
        $items = array_map(static fn (string $tag): array => ['tag' => $tag], $this->tags);

        return $this->paginate($items, $cursor);
    }

    #[Override]
    public function firstTagContaining(BindingContext $context, string $commitSha): ?string
    {
        return $this->tags[0] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function paginate(array $items, ?string $cursor): Page
    {
        $offset = $cursor === null ? 0 : (int) $cursor;
        $slice = array_slice($items, $offset, self::PAGE_SIZE);
        $next = $offset + self::PAGE_SIZE;

        return new Page(
            array_values($slice),
            nextCursor: $next < count($items) ? (string) $next : null,
        );
    }
}
