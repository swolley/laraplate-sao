<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\External;

use Illuminate\Http\Client\Response;
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
 * The `issues` driver for Linear, over its GraphQL API. Authentication is a
 * personal API key in the `Authorization` header; the binding's
 * `remoteIdentifier` is the team key (e.g. `ENG`), and `config.team_id` supplies
 * the team UUID needed to create issues. Listing follows Linear's cursor
 * pagination (`pageInfo.endCursor`). The canonical `remote_id` is the issue
 * UUID (what `issue(id:)` accepts); the human `identifier` (`ENG-12`) is `key`.
 */
final readonly class LinearDriver implements DriverInterface, IssuesCapability
{
    private const int DEFAULT_PAGE_SIZE = 25;

    private const string ISSUE_FIELDS = 'id identifier title description url createdAt updatedAt state { name } priorityLabel assignee { name }';

    #[Override]
    public function key(): string
    {
        return 'linear';
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
            new ConfigurationField('token', 'string', 'Linear personal API key', required: true, secret: true),
            new ConfigurationField('team_id', 'string', 'Team UUID used when creating issues', required: false),
            new ConfigurationField('page_size', 'integer', 'Issues fetched per page', required: false),
        ]);
    }

    #[Override]
    public function healthCheck(ConnectionContext $context): HealthCheckResult
    {
        if ($context->baseUrl === null) {
            return HealthCheckResult::unhealthy('No base URL configured for the Linear connection.');
        }

        try {
            $response = $this->gql($context->credentials, (string) $context->baseUrl, 'query { viewer { id } }', []);
        } catch (Throwable $exception) {
            return HealthCheckResult::unhealthy($exception->getMessage());
        }

        return $response->successful() && $response->json('data.viewer.id') !== null
            ? HealthCheckResult::healthy()
            : HealthCheckResult::unhealthy("Linear returned HTTP {$response->status()}.");
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Override]
    public function lookup(BindingContext $context, string $remoteId): ?array
    {
        $query = 'query($id: String!) { issue(id: $id) { ' . self::ISSUE_FIELDS . ' } }';

        $response = $this->gql($context->connection->credentials, (string) $context->baseUrl(), $query, ['id' => $remoteId]);
        $issue = $response->json('data.issue');

        return is_array($issue) ? $this->normalize($context, $issue)->toArray() : null;
    }

    #[Override]
    public function list(BindingContext $context, ?string $cursor = null): Page
    {
        $query = 'query($after: String, $first: Int!, $team: String!) {'
            . ' issues(first: $first, after: $after, filter: { team: { key: { eq: $team } } }) {'
            . ' nodes { ' . self::ISSUE_FIELDS . ' } pageInfo { hasNextPage endCursor } } }';

        $response = $this->gql($context->connection->credentials, (string) $context->baseUrl(), $query, [
            'after' => $cursor,
            'first' => $this->pageSize($context),
            'team' => (string) $context->remoteIdentifier,
        ]);

        /** @var list<array<string, mixed>> $nodes */
        $nodes = $response->json('data.issues.nodes', []);
        $items = array_map(fn (array $issue): array => $this->normalize($context, $issue)->toArray(), $nodes);

        $hasNext = (bool) $response->json('data.issues.pageInfo.hasNextPage', false);
        $endCursor = $response->json('data.issues.pageInfo.endCursor');

        return new Page(
            array_values($items),
            nextCursor: $hasNext && is_string($endCursor) ? $endCursor : null,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function create(BindingContext $context, array $attributes): array
    {
        $mutation = 'mutation($input: IssueCreateInput!) { issueCreate(input: $input) { issue { ' . self::ISSUE_FIELDS . ' } } }';

        $input = array_merge(
            ['teamId' => $context->config['team_id'] ?? $context->remoteIdentifier],
            $this->toLinearInput($attributes),
        );

        $response = $this->gql($context->connection->credentials, (string) $context->baseUrl(), $mutation, ['input' => $input]);

        /** @var array<string, mixed> $issue */
        $issue = $response->json('data.issueCreate.issue', []);

        return $this->normalize($context, $issue)->toArray();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function update(BindingContext $context, string $remoteId, array $attributes): array
    {
        $mutation = 'mutation($id: String!, $input: IssueUpdateInput!) { issueUpdate(id: $id, input: $input) { issue { ' . self::ISSUE_FIELDS . ' } } }';

        $response = $this->gql($context->connection->credentials, (string) $context->baseUrl(), $mutation, [
            'id' => $remoteId,
            'input' => $this->toLinearInput($attributes),
        ]);

        /** @var array<string, mixed> $issue */
        $issue = $response->json('data.issueUpdate.issue', []);

        return isset($issue['id']) ? $this->normalize($context, $issue)->toArray() : ['remote_id' => $remoteId];
    }

    #[Override]
    public function comment(BindingContext $context, string $remoteId, string $body): void
    {
        $mutation = 'mutation($input: CommentCreateInput!) { commentCreate(input: $input) { success } }';

        $this->gql($context->connection->credentials, (string) $context->baseUrl(), $mutation, [
            'input' => ['issueId' => $remoteId, 'body' => $body],
        ]);
    }

    /**
     * @param  array<string, string>  $statusMap  Remote state name → canonical category.
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
    private function toLinearInput(array $attributes): array
    {
        $input = [];

        if (array_key_exists('title', $attributes)) {
            $input['title'] = $attributes['title'];
        }

        $body = $attributes['body'] ?? $attributes['description'] ?? null;

        if ($body !== null) {
            $input['description'] = $body;
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function normalize(BindingContext $context, array $issue): NormalizedIssue
    {
        $identifier = isset($issue['identifier']) ? (string) $issue['identifier'] : null;
        $state = $issue['state'] ?? null;
        $assignee = $issue['assignee'] ?? null;

        return new NormalizedIssue(
            remoteId: (string) ($issue['id'] ?? ''),
            title: (string) ($issue['title'] ?? ''),
            key: $identifier,
            body: isset($issue['description']) ? (string) $issue['description'] : null,
            remoteStatus: is_array($state) && isset($state['name']) ? (string) $state['name'] : null,
            remotePriority: isset($issue['priorityLabel']) ? (string) $issue['priorityLabel'] : null,
            assignee: is_array($assignee) && isset($assignee['name']) ? (string) $assignee['name'] : null,
            url: isset($issue['url']) ? (string) $issue['url'] : null,
            createdAt: isset($issue['createdAt']) ? (string) $issue['createdAt'] : null,
            updatedAt: isset($issue['updatedAt']) ? (string) $issue['updatedAt'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $variables
     */
    private function gql(array $credentials, string $baseUrl, string $query, array $variables): Response
    {
        $token = (string) ($credentials['token'] ?? '');

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['Authorization' => $token])
            ->post('/graphql', ['query' => $query, 'variables' => $variables]);
    }

    private function pageSize(BindingContext $context): int
    {
        $configured = $context->config['page_size'] ?? null;

        return $configured === null ? self::DEFAULT_PAGE_SIZE : max(1, (int) $configured);
    }
}
