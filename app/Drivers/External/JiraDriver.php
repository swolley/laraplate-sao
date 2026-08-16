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
 * The `issues` driver for Jira Cloud, over the REST v3 API. Authentication is
 * Basic (account email + API token); the binding's `remoteIdentifier` is the
 * Jira project key, used in the JQL of the search endpoint. Statuses are
 * per-project workflows, so `translateStatus` is a plain lookup over the
 * binding map — the driver never hardcodes a meaning.
 */
final readonly class JiraDriver implements DriverInterface, IssuesCapability
{
    private const int DEFAULT_PAGE_SIZE = 50;

    #[Override]
    public function key(): string
    {
        return 'jira';
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
            new ConfigurationField('email', 'string', 'Account email', required: true),
            new ConfigurationField('token', 'string', 'Jira API token', required: true, secret: true),
            new ConfigurationField('issue_type', 'string', 'Default issue type for created tickets', required: false),
            new ConfigurationField('page_size', 'integer', 'Issues fetched per page', required: false),
        ]);
    }

    #[Override]
    public function healthCheck(ConnectionContext $context): HealthCheckResult
    {
        if ($context->baseUrl === null) {
            return HealthCheckResult::unhealthy('No base URL configured for the Jira connection.');
        }

        try {
            $response = $this->client($context)->get('/rest/api/3/myself');
        } catch (Throwable $exception) {
            return HealthCheckResult::unhealthy($exception->getMessage());
        }

        return $response->successful()
            ? HealthCheckResult::healthy()
            : HealthCheckResult::unhealthy("Jira returned HTTP {$response->status()}.");
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Override]
    public function lookup(BindingContext $context, string $remoteId): ?array
    {
        $response = $this->connection($context)->get("/rest/api/3/issue/{$remoteId}");

        if ($response->status() === 404) {
            return null;
        }

        $issue = $response->json();

        return is_array($issue) && isset($issue['id']) ? $this->normalize($context, $issue)->toArray() : null;
    }

    #[Override]
    public function list(BindingContext $context, ?string $cursor = null): Page
    {
        $startAt = $cursor === null ? 0 : (int) $cursor;
        $maxResults = $this->pageSize($context);

        $jql = $context->remoteIdentifier !== null
            ? "project = \"{$context->remoteIdentifier}\" ORDER BY created ASC"
            : 'ORDER BY created ASC';

        $response = $this->connection($context)->get('/rest/api/3/search', [
            'jql' => $jql,
            'startAt' => $startAt,
            'maxResults' => $maxResults,
        ]);

        /** @var list<array<string, mixed>> $issues */
        $issues = $response->json('issues', []);
        $total = (int) $response->json('total', count($issues));

        $items = array_map(
            fn (array $issue): array => $this->normalize($context, $issue)->toArray(),
            $issues,
        );

        $next = $startAt + $maxResults;

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
        $response = $this->connection($context)->post('/rest/api/3/issue', [
            'fields' => $this->toJiraFields($context, $attributes, forCreation: true),
        ]);

        $id = (string) ($response->json('id') ?? '');

        // The create response carries only id/key/self, so the normalized state
        // is read back from the issue endpoint.
        return $this->lookup($context, $id) ?? ['remote_id' => $id, 'key' => $response->json('key')];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function update(BindingContext $context, string $remoteId, array $attributes): array
    {
        $this->connection($context)->put("/rest/api/3/issue/{$remoteId}", [
            'fields' => $this->toJiraFields($context, $attributes),
        ]);

        return $this->lookup($context, $remoteId) ?? ['remote_id' => $remoteId];
    }

    #[Override]
    public function comment(BindingContext $context, string $remoteId, string $body): void
    {
        $this->connection($context)->post("/rest/api/3/issue/{$remoteId}/comment", [
            'body' => $body,
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
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function toJiraFields(BindingContext $context, array $attributes, bool $forCreation = false): array
    {
        $fields = [];

        if (array_key_exists('title', $attributes)) {
            $fields['summary'] = $attributes['title'];
        }

        if (array_key_exists('body', $attributes)) {
            $fields['description'] = $attributes['body'];
        } elseif (array_key_exists('description', $attributes)) {
            $fields['description'] = $attributes['description'];
        }

        if ($forCreation) {
            if ($context->remoteIdentifier !== null) {
                $fields['project'] = ['key' => $context->remoteIdentifier];
            }

            $fields['issuetype'] = ['name' => (string) ($context->config['issue_type'] ?? 'Task')];
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function normalize(BindingContext $context, array $issue): NormalizedIssue
    {
        /** @var array<string, mixed> $fields */
        $fields = $issue['fields'] ?? [];
        $key = isset($issue['key']) ? (string) $issue['key'] : null;
        $base = $context->baseUrl();

        return new NormalizedIssue(
            remoteId: (string) ($issue['id'] ?? ''),
            title: (string) ($fields['summary'] ?? ''),
            key: $key,
            body: isset($fields['description']) && is_string($fields['description']) ? $fields['description'] : null,
            remoteStatus: $this->nestedName($fields, 'status'),
            remotePriority: $this->nestedName($fields, 'priority'),
            assignee: $this->assigneeName($fields),
            url: $base !== null && $key !== null ? mb_rtrim($base, '/') . "/browse/{$key}" : null,
            createdAt: isset($fields['created']) ? (string) $fields['created'] : null,
            updatedAt: isset($fields['updated']) ? (string) $fields['updated'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function nestedName(array $fields, string $key): ?string
    {
        $value = $fields[$key] ?? null;

        return is_array($value) && isset($value['name']) ? (string) $value['name'] : null;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function assigneeName(array $fields): ?string
    {
        $assignee = $fields['assignee'] ?? null;

        return is_array($assignee) && isset($assignee['displayName']) ? (string) $assignee['displayName'] : null;
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
        $email = (string) ($context->credentials['email'] ?? '');
        $token = (string) ($context->credentials['token'] ?? '');

        return Http::baseUrl((string) $context->baseUrl)
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($email, $token);
    }
}
