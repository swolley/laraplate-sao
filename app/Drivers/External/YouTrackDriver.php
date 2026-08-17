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
 * The `issues` driver for YouTrack, over its REST API (`/api/issues`).
 * Authentication is a permanent token as a bearer; the binding's
 * `remoteIdentifier` is the project short name. YouTrack paginates with
 * `$skip`/`$top` and returns no total, so the driver advances the cursor while a
 * full page comes back. Status/priority/assignee live in custom fields, read by
 * name; `translateStatus` is a plain lookup over the binding-provided map.
 */
final readonly class YouTrackDriver implements DriverInterface, IssuesCapability
{
    private const int DEFAULT_PAGE_SIZE = 25;

    private const string FIELDS = 'idReadable,summary,description,created,updated,customFields(name,value(name,login,fullName))';

    #[Override]
    public function key(): string
    {
        return 'youtrack';
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
            new ConfigurationField('token', 'string', 'YouTrack permanent token', required: true, secret: true),
            new ConfigurationField('page_size', 'integer', 'Issues fetched per page', required: false),
        ]);
    }

    #[Override]
    public function healthCheck(ConnectionContext $context): HealthCheckResult
    {
        if ($context->baseUrl === null) {
            return HealthCheckResult::unhealthy('No base URL configured for the YouTrack connection.');
        }

        try {
            $response = $this->client($context)->get('/api/users/me', ['fields' => 'login']);
        } catch (Throwable $exception) {
            return HealthCheckResult::unhealthy($exception->getMessage());
        }

        return $response->successful()
            ? HealthCheckResult::healthy()
            : HealthCheckResult::unhealthy("YouTrack returned HTTP {$response->status()}.");
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Override]
    public function lookup(BindingContext $context, string $remoteId): ?array
    {
        $response = $this->connection($context)->get("/api/issues/{$remoteId}", ['fields' => self::FIELDS]);

        if ($response->status() === 404) {
            return null;
        }

        $issue = $response->json();

        return is_array($issue) && isset($issue['idReadable']) ? $this->normalize($context, $issue)->toArray() : null;
    }

    #[Override]
    public function list(BindingContext $context, ?string $cursor = null): Page
    {
        $skip = $cursor === null ? 0 : (int) $cursor;
        $top = $this->pageSize($context);

        $query = [
            'fields' => self::FIELDS,
            '$skip' => $skip,
            '$top' => $top,
        ];

        if ($context->remoteIdentifier !== null) {
            $query['query'] = 'project: ' . $context->remoteIdentifier;
        }

        $response = $this->connection($context)->get('/api/issues', $query);

        /** @var list<array<string, mixed>> $rows */
        $rows = $response->json() ?? [];

        $items = array_map(fn (array $issue): array => $this->normalize($context, $issue)->toArray(), $rows);

        // No total is returned; a full page means there may be more.
        return new Page(
            array_values($items),
            nextCursor: count($rows) === $top ? (string) ($skip + $top) : null,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function create(BindingContext $context, array $attributes): array
    {
        $response = $this->connection($context)->post('/api/issues', array_merge(
            ['project' => ['shortName' => $attributes['project'] ?? $context->remoteIdentifier]],
            $this->toYouTrackIssue($attributes),
        ));

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
        $this->connection($context)->post("/api/issues/{$remoteId}", $this->toYouTrackIssue($attributes));

        return $this->lookup($context, $remoteId) ?? ['remote_id' => $remoteId];
    }

    #[Override]
    public function comment(BindingContext $context, string $remoteId, string $body): void
    {
        $this->connection($context)->post("/api/issues/{$remoteId}/comments", ['text' => $body]);
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
    private function toYouTrackIssue(array $attributes): array
    {
        $issue = [];

        if (array_key_exists('title', $attributes)) {
            $issue['summary'] = $attributes['title'];
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
        $readable = (string) ($issue['idReadable'] ?? '');
        $base = $context->baseUrl();
        $fields = $this->customFields($issue);

        return new NormalizedIssue(
            remoteId: $readable,
            title: (string) ($issue['summary'] ?? ''),
            key: $readable !== '' ? $readable : null,
            body: isset($issue['description']) ? (string) $issue['description'] : null,
            remoteStatus: $fields['State'] ?? null,
            remotePriority: $fields['Priority'] ?? null,
            assignee: $fields['Assignee'] ?? null,
            url: $base !== null && $readable !== '' ? mb_rtrim($base, '/') . "/issue/{$readable}" : null,
            createdAt: $this->timestamp($issue['created'] ?? null),
            updatedAt: $this->timestamp($issue['updated'] ?? null),
        );
    }

    /**
     * Flatten the custom-field array to `name => value label`.
     *
     * @param  array<string, mixed>  $issue
     * @return array<string, string>
     */
    private function customFields(array $issue): array
    {
        $flattened = [];

        /** @var list<array<string, mixed>> $fields */
        $fields = is_array($issue['customFields'] ?? null) ? $issue['customFields'] : [];

        foreach ($fields as $field) {
            if (! is_array($field) || ! isset($field['name'])) {
                continue;
            }

            $value = $field['value'] ?? null;
            $label = is_array($value)
                ? ($value['name'] ?? $value['fullName'] ?? $value['login'] ?? null)
                : null;

            if (is_string($label) && $label !== '') {
                $flattened[(string) $field['name']] = $label;
            }
        }

        return $flattened;
    }

    private function timestamp(mixed $value): ?string
    {
        return is_int($value) ? (string) $value : (is_string($value) && $value !== '' ? $value : null);
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
            ->withToken($token);
    }
}
