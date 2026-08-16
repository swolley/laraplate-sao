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
 * The first concrete external issues driver, over the documented Redmine REST
 * API (`/issues.json`, `/projects.json`). Authentication is the
 * `X-Redmine-API-Key` header; the binding's `remoteIdentifier` is the Redmine
 * `project_id`. Statuses are per-installation, so `translateStatus` is a plain
 * lookup over the binding-provided map — the driver never hardcodes a meaning.
 *
 * The driver operates only on a resolved {@see BindingContext} and the `Http`
 * client, never on the Eloquent model, keeping `app/Drivers` free of
 * persistence.
 */
final readonly class RedmineDriver implements DriverInterface, IssuesCapability
{
    private const int DEFAULT_PAGE_SIZE = 25;

    #[Override]
    public function key(): string
    {
        return 'redmine';
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
        // Redmine core has no outbound webhook; SAO polls it.
        return [IngestMode::Pull];
    }

    #[Override]
    public function configurationSchema(): DriverConfigurationSchema
    {
        return new DriverConfigurationSchema([
            new ConfigurationField('token', 'string', 'Redmine API key', required: true, secret: true),
            new ConfigurationField('page_size', 'integer', 'Issues fetched per page', required: false),
        ]);
    }

    #[Override]
    public function healthCheck(ConnectionContext $context): HealthCheckResult
    {
        if ($context->baseUrl === null) {
            return HealthCheckResult::unhealthy('No base URL configured for the Redmine connection.');
        }

        try {
            $response = $this->client($context)->get('/projects.json', ['limit' => 1]);
        } catch (Throwable $exception) {
            return HealthCheckResult::unhealthy($exception->getMessage());
        }

        return $response->successful()
            ? HealthCheckResult::healthy()
            : HealthCheckResult::unhealthy("Redmine returned HTTP {$response->status()}.");
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Override]
    public function lookup(BindingContext $context, string $remoteId): ?array
    {
        $response = $this->connection($context)->get("/issues/{$remoteId}.json");

        if ($response->status() === 404) {
            return null;
        }

        $issue = $response->json('issue');

        return is_array($issue) ? $this->normalize($context, $issue)->toArray() : null;
    }

    #[Override]
    public function list(BindingContext $context, ?string $cursor = null): Page
    {
        $offset = $cursor === null ? 0 : (int) $cursor;
        $limit = $this->pageSize($context);

        $query = [
            'offset' => $offset,
            'limit' => $limit,
            'status_id' => '*',
        ];

        if ($context->remoteIdentifier !== null) {
            $query['project_id'] = $context->remoteIdentifier;
        }

        $response = $this->connection($context)->get('/issues.json', $query);

        /** @var list<array<string, mixed>> $issues */
        $issues = $response->json('issues', []);
        $total = (int) $response->json('total_count', count($issues));

        $items = array_map(
            fn (array $issue): array => $this->normalize($context, $issue)->toArray(),
            $issues,
        );

        $next = $offset + $limit;

        return new Page(
            array_values($items),
            nextCursor: $next < $total ? (string) $next : null,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function create(BindingContext $context, array $attributes): array
    {
        $response = $this->connection($context)->post('/issues.json', [
            'issue' => $this->toRedmineIssue($context, $attributes),
        ]);

        /** @var array<string, mixed> $issue */
        $issue = $response->json('issue', []);

        return $this->normalize($context, $issue)->toArray();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function update(BindingContext $context, string $remoteId, array $attributes): array
    {
        // Redmine answers a successful PUT with 204 No Content, so the updated
        // state is read back rather than parsed from the write response.
        $this->connection($context)->put("/issues/{$remoteId}.json", [
            'issue' => $this->toRedmineIssue($context, $attributes),
        ]);

        return $this->lookup($context, $remoteId) ?? ['remote_id' => $remoteId];
    }

    #[Override]
    public function comment(BindingContext $context, string $remoteId, string $body): void
    {
        $this->connection($context)->put("/issues/{$remoteId}.json", [
            'issue' => ['notes' => $body],
        ]);
    }

    /**
     * @param  array<string, string>  $statusMap  Remote status name → canonical category.
     */
    #[Override]
    public function translateStatus(array $statusMap, string $remoteStatus): ?string
    {
        return $statusMap[$remoteStatus] ?? null;
    }

    /**
     * Map SAO's canonical attribute names onto Redmine issue fields.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function toRedmineIssue(BindingContext $context, array $attributes): array
    {
        $issue = [];

        if (array_key_exists('title', $attributes)) {
            $issue['subject'] = $attributes['title'];
        }

        if (array_key_exists('body', $attributes)) {
            $issue['description'] = $attributes['body'];
        } elseif (array_key_exists('description', $attributes)) {
            $issue['description'] = $attributes['description'];
        }

        $projectId = $attributes['project_id'] ?? $context->remoteIdentifier;

        if ($projectId !== null && ! isset($issue['project_id'])) {
            $issue['project_id'] = $projectId;
        }

        return $issue;
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function normalize(BindingContext $context, array $issue): NormalizedIssue
    {
        $remoteId = (string) ($issue['id'] ?? '');
        $base = $context->baseUrl();

        return new NormalizedIssue(
            remoteId: $remoteId,
            title: (string) ($issue['subject'] ?? ''),
            body: isset($issue['description']) ? (string) $issue['description'] : null,
            remoteStatus: $this->nestedName($issue, 'status'),
            remotePriority: $this->nestedName($issue, 'priority'),
            assignee: $this->nestedName($issue, 'assigned_to'),
            url: $base !== null && $remoteId !== '' ? mb_rtrim($base, '/') . "/issues/{$remoteId}" : null,
            createdAt: isset($issue['created_on']) ? (string) $issue['created_on'] : null,
            updatedAt: isset($issue['updated_on']) ? (string) $issue['updated_on'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function nestedName(array $issue, string $key): ?string
    {
        $value = $issue[$key] ?? null;

        return is_array($value) && isset($value['name']) ? (string) $value['name'] : null;
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
        $token = $context->credentials['token'] ?? null;

        return Http::baseUrl((string) $context->baseUrl)
            ->acceptJson()
            ->asJson()
            ->when($token !== null, static fn (PendingRequest $request): PendingRequest => $request->withHeaders([
                'X-Redmine-API-Key' => (string) $token,
            ]));
    }
}
