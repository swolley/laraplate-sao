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
 * The `issues` driver for GitLab, over the REST v4 API. Authentication is the
 * `PRIVATE-TOKEN` header; the binding's `remoteIdentifier` is the project id (a
 * numeric id or a URL-encoded path). Issues are addressed by their project-scoped
 * `iid`. Pagination follows the `X-Next-Page` response header. The status is the
 * issue `state` (opened/closed), mapped through the binding map.
 */
final readonly class GitLabDriver implements DriverInterface, IssuesCapability
{
    private const int DEFAULT_PAGE_SIZE = 20;

    #[Override]
    public function key(): string
    {
        return 'gitlab';
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
            new ConfigurationField('token', 'string', 'GitLab personal or project access token', required: true, secret: true),
            new ConfigurationField('page_size', 'integer', 'Issues fetched per page', required: false),
        ]);
    }

    #[Override]
    public function healthCheck(ConnectionContext $context): HealthCheckResult
    {
        if ($context->baseUrl === null) {
            return HealthCheckResult::unhealthy('No base URL configured for the GitLab connection.');
        }

        try {
            $response = $this->client($context)->get('/api/v4/version');
        } catch (Throwable $exception) {
            return HealthCheckResult::unhealthy($exception->getMessage());
        }

        return $response->successful()
            ? HealthCheckResult::healthy()
            : HealthCheckResult::unhealthy("GitLab returned HTTP {$response->status()}.");
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Override]
    public function lookup(BindingContext $context, string $remoteId): ?array
    {
        $response = $this->connection($context)->get($this->issuesPath($context) . "/{$remoteId}");

        if ($response->status() === 404) {
            return null;
        }

        $issue = $response->json();

        return is_array($issue) && isset($issue['iid']) ? $this->normalize($context, $issue)->toArray() : null;
    }

    #[Override]
    public function list(BindingContext $context, ?string $cursor = null): Page
    {
        $page = $cursor === null ? 1 : (int) $cursor;

        $response = $this->connection($context)->get($this->issuesPath($context), [
            'per_page' => $this->pageSize($context),
            'page' => $page,
        ]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $response->json() ?? [];

        $items = array_map(
            fn (array $issue): array => $this->normalize($context, $issue)->toArray(),
            $rows,
        );

        $nextPage = $response->header('X-Next-Page');

        return new Page(
            array_values($items),
            nextCursor: $nextPage === null || $nextPage === '' ? null : $nextPage,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function create(BindingContext $context, array $attributes): array
    {
        $response = $this->connection($context)->post($this->issuesPath($context), $this->toGitLabIssue($attributes));

        /** @var array<string, mixed> $issue */
        $issue = $response->json() ?? [];

        return $this->normalize($context, $issue)->toArray();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function update(BindingContext $context, string $remoteId, array $attributes): array
    {
        $response = $this->connection($context)->put($this->issuesPath($context) . "/{$remoteId}", $this->toGitLabIssue($attributes));

        /** @var array<string, mixed> $issue */
        $issue = $response->json() ?? [];

        return $this->normalize($context, $issue)->toArray();
    }

    #[Override]
    public function comment(BindingContext $context, string $remoteId, string $body): void
    {
        $this->connection($context)->post($this->issuesPath($context) . "/{$remoteId}/notes", [
            'body' => $body,
        ]);
    }

    /**
     * @param  array<string, string>  $statusMap  Remote state (opened/closed) → canonical category.
     */
    #[Override]
    public function translateStatus(array $statusMap, string $remoteStatus): ?string
    {
        return $statusMap[$remoteStatus] ?? null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function toGitLabIssue(array $attributes): array
    {
        $issue = [];

        if (array_key_exists('title', $attributes)) {
            $issue['title'] = $attributes['title'];
        }

        if (array_key_exists('body', $attributes)) {
            $issue['description'] = $attributes['body'];
        } elseif (array_key_exists('description', $attributes)) {
            $issue['description'] = $attributes['description'];
        }

        return $issue;
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function normalize(BindingContext $context, array $issue): NormalizedIssue
    {
        $iid = (string) ($issue['iid'] ?? '');
        $project = $context->remoteIdentifier;

        return new NormalizedIssue(
            remoteId: $iid,
            title: (string) ($issue['title'] ?? ''),
            key: $project !== null && $iid !== '' ? "{$project}#{$iid}" : null,
            body: isset($issue['description']) ? (string) $issue['description'] : null,
            remoteStatus: isset($issue['state']) ? (string) $issue['state'] : null,
            remotePriority: null,
            assignee: $this->assigneeUsername($issue),
            url: isset($issue['web_url']) ? (string) $issue['web_url'] : null,
            createdAt: isset($issue['created_at']) ? (string) $issue['created_at'] : null,
            updatedAt: isset($issue['updated_at']) ? (string) $issue['updated_at'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function assigneeUsername(array $issue): ?string
    {
        $assignee = $issue['assignee'] ?? null;

        return is_array($assignee) && isset($assignee['username']) ? (string) $assignee['username'] : null;
    }

    private function issuesPath(BindingContext $context): string
    {
        return '/api/v4/projects/' . (string) $context->remoteIdentifier . '/issues';
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
            ->asJson()
            ->withHeaders(['PRIVATE-TOKEN' => $token]);
    }
}
