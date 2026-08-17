<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\External;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Drivers\Contracts\DriverInterface;
use Modules\SAO\Drivers\Contracts\IssuesCapability;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConfigurationField;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Drivers\Support\DriverConfigurationSchema;
use Modules\SAO\Drivers\Support\HealthCheckResult;
use Modules\SAO\Drivers\Support\NormalizedIssue;
use Modules\SAO\Drivers\Support\Page;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Override;
use Throwable;

/**
 * The `issues` driver for Azure DevOps Boards, over the work-item REST API.
 * Authentication is a personal access token via Basic auth; the binding's
 * `remoteIdentifier` is the project name. Listing is the documented two step —
 * a WIQL query returns the ordered ids, then a batch read fetches their fields —
 * and the cursor is an offset into that id list. Writes use the JSON-Patch
 * document Azure requires. `translateStatus` is a plain lookup over the
 * binding-provided map.
 */
final readonly class AzureDevOpsDriver implements DriverInterface, IssuesCapability
{
    private const int DEFAULT_PAGE_SIZE = 25;

    private const string API_VERSION = '7.0';

    #[Override]
    public function key(): string
    {
        return 'azure_devops';
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
        return new DriverConfigurationSchema([
            new ConfigurationField('token', 'string', 'Azure DevOps personal access token', required: true, secret: true),
            new ConfigurationField('work_item_type', 'string', 'Work item type for created issues (e.g. Issue, Bug)', required: false),
            new ConfigurationField('page_size', 'integer', 'Issues fetched per page', required: false),
        ]);
    }

    #[Override]
    public function healthCheck(ConnectionContext $context): HealthCheckResult
    {
        if ($context->baseUrl === null) {
            return HealthCheckResult::unhealthy('No base URL configured for the Azure DevOps connection.');
        }

        try {
            $response = $this->client($context)->get('/_apis/projects', ['api-version' => self::API_VERSION, '$top' => 1]);
        } catch (Throwable $exception) {
            return HealthCheckResult::unhealthy($exception->getMessage());
        }

        return $response->successful()
            ? HealthCheckResult::healthy()
            : HealthCheckResult::unhealthy("Azure DevOps returned HTTP {$response->status()}.");
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Override]
    public function lookup(BindingContext $context, string $remoteId): ?array
    {
        $response = $this->connection($context)->get(
            $this->projectPath($context) . "/_apis/wit/workitems/{$remoteId}",
            ['api-version' => self::API_VERSION],
        );

        if ($response->status() === 404) {
            return null;
        }

        $item = $response->json();

        return is_array($item) && isset($item['id']) ? $this->normalize($context, $item)->toArray() : null;
    }

    #[Override]
    public function list(BindingContext $context, ?string $cursor = null): Page
    {
        $offset = $cursor === null ? 0 : (int) $cursor;
        $limit = $this->pageSize($context);

        $wiql = $this->connection($context)->post(
            $this->projectPath($context) . '/_apis/wit/wiql?api-version=' . self::API_VERSION,
            ['query' => 'SELECT [System.Id] FROM WorkItems ORDER BY [System.Id] ASC'],
        );

        /** @var list<array<string, mixed>> $workItems */
        $workItems = $wiql->json('workItems', []);
        $ids = array_values(array_filter(array_map(static fn (array $row): ?int => isset($row['id']) ? (int) $row['id'] : null, $workItems)));

        $slice = array_slice($ids, $offset, $limit);

        if ($slice === []) {
            return new Page([]);
        }

        $batch = $this->connection($context)->get($this->projectPath($context) . '/_apis/wit/workitems', [
            'ids' => implode(',', $slice),
            'api-version' => self::API_VERSION,
        ]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $batch->json('value', []);
        $items = array_map(fn (array $item): array => $this->normalize($context, $item)->toArray(), $rows);

        $next = $offset + $limit;

        return new Page(
            array_values($items),
            nextCursor: $next < count($ids) ? (string) $next : null,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function create(BindingContext $context, array $attributes): array
    {
        $type = (string) ($context->config['work_item_type'] ?? 'Issue');

        $response = $this->patchDocument(
            $context,
            'POST',
            $this->projectPath($context) . '/_apis/wit/workitems/$' . rawurlencode($type),
            $this->toPatchOps($attributes),
        );

        /** @var array<string, mixed> $item */
        $item = $response->json() ?? [];

        return $this->normalize($context, $item)->toArray();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function update(BindingContext $context, string $remoteId, array $attributes): array
    {
        $response = $this->patchDocument(
            $context,
            'PATCH',
            $this->projectPath($context) . "/_apis/wit/workitems/{$remoteId}",
            $this->toPatchOps($attributes),
        );

        /** @var array<string, mixed> $item */
        $item = $response->json() ?? [];

        return isset($item['id']) ? $this->normalize($context, $item)->toArray() : ['remote_id' => $remoteId];
    }

    #[Override]
    public function comment(BindingContext $context, string $remoteId, string $body): void
    {
        $this->connection($context)->post(
            $this->projectPath($context) . "/_apis/wit/workItems/{$remoteId}/comments?api-version=" . self::API_VERSION . '-preview.3',
            ['text' => $body],
        );
    }

    /**
     * @param  array<string, string>  $statusMap  Remote state → canonical category.
     */
    #[Override]
    public function translateStatus(array $statusMap, string $remoteStatus): ?string
    {
        return $statusMap[$remoteStatus] ?? null;
    }

    /**
     * Build the JSON-Patch add operations Azure requires for a write.
     *
     * @param  array<string, mixed>  $attributes
     * @return list<array{op: string, path: string, value: mixed}>
     */
    private function toPatchOps(array $attributes): array
    {
        $ops = [];

        if (array_key_exists('title', $attributes)) {
            $ops[] = ['op' => 'add', 'path' => '/fields/System.Title', 'value' => $attributes['title']];
        }

        $body = $attributes['body'] ?? $attributes['description'] ?? null;

        if ($body !== null) {
            $ops[] = ['op' => 'add', 'path' => '/fields/System.Description', 'value' => $body];
        }

        return $ops;
    }

    /**
     * @param  list<array{op: string, path: string, value: mixed}>  $ops
     */
    private function patchDocument(BindingContext $context, string $method, string $url, array $ops): \Illuminate\Http\Client\Response
    {
        $request = $this->connection($context)
            ->withBody((string) json_encode($ops), 'application/json-patch+json');

        $url .= (str_contains($url, '?') ? '&' : '?') . 'api-version=' . self::API_VERSION;

        return $method === 'POST' ? $request->post($url) : $request->patch($url);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function normalize(BindingContext $context, array $item): NormalizedIssue
    {
        $id = (string) ($item['id'] ?? '');
        /** @var array<string, mixed> $fields */
        $fields = is_array($item['fields'] ?? null) ? $item['fields'] : [];

        $assignee = $fields['System.AssignedTo'] ?? null;
        $priority = $fields['Microsoft.VSTS.Common.Priority'] ?? null;

        return new NormalizedIssue(
            remoteId: $id,
            title: (string) ($fields['System.Title'] ?? ''),
            body: isset($fields['System.Description']) ? (string) $fields['System.Description'] : null,
            remoteStatus: isset($fields['System.State']) ? (string) $fields['System.State'] : null,
            remotePriority: $priority !== null ? (string) $priority : null,
            assignee: is_array($assignee) && isset($assignee['displayName']) ? (string) $assignee['displayName'] : null,
            url: $this->htmlUrl($context, $item, $id),
            createdAt: isset($fields['System.CreatedDate']) ? (string) $fields['System.CreatedDate'] : null,
            updatedAt: isset($fields['System.ChangedDate']) ? (string) $fields['System.ChangedDate'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function htmlUrl(BindingContext $context, array $item, string $id): ?string
    {
        $links = $item['_links'] ?? null;

        if (is_array($links) && isset($links['html']['href'])) {
            return (string) $links['html']['href'];
        }

        $base = $context->baseUrl();

        return $base !== null && $id !== '' ? mb_rtrim($base, '/') . '/_workitems/edit/' . $id : null;
    }

    private function projectPath(BindingContext $context): string
    {
        return '/' . rawurlencode((string) $context->remoteIdentifier);
    }

    private function pageSize(BindingContext $context): int
    {
        $configured = $context->config['page_size'] ?? null;

        return $configured === null ? self::DEFAULT_PAGE_SIZE : max(1, (int) $configured);
    }

    private function connection(BindingContext $context): PendingRequest
    {
        return $this->client($context->connection);
    }

    private function client(ConnectionContext $context): PendingRequest
    {
        $token = (string) ($context->credentials['token'] ?? '');

        return Http::baseUrl((string) $context->baseUrl)
            ->acceptJson()
            ->withBasicAuth('', $token);
    }
}
