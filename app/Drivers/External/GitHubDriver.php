<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\External;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Drivers\Contracts\DriverInterface;
use Modules\SAO\Drivers\Contracts\IssuesCapability;
use Modules\SAO\Drivers\Contracts\ReleasesCapability;
use Modules\SAO\Drivers\Contracts\VcsCapability;
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
 * The `issues` driver for GitHub, over the REST API. Authentication is a bearer
 * token; the binding's `remoteIdentifier` is `owner/repo`. Pagination follows
 * the `Link` header's `rel="next"`. GitHub has no native priority, and its
 * status is the issue `state` (open/closed) — `translateStatus` maps that
 * through the binding map. Pull requests share the issues endpoint and are
 * filtered out.
 */
final readonly class GitHubDriver implements DriverInterface, IssuesCapability, ReleasesCapability, VcsCapability
{
    private const int DEFAULT_PAGE_SIZE = 30;

    #[Override]
    public function key(): string
    {
        return 'github';
    }

    /**
     * @return list<Capability>
     */
    #[Override]
    public function capabilities(): array
    {
        return [Capability::Issues, Capability::Vcs, Capability::Releases];
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
            new ConfigurationField('token', 'string', 'GitHub personal access or app token', required: true, secret: true),
            new ConfigurationField('page_size', 'integer', 'Issues fetched per page', required: false),
        ]);
    }

    #[Override]
    public function healthCheck(ConnectionContext $context): HealthCheckResult
    {
        if ($context->baseUrl === null) {
            return HealthCheckResult::unhealthy('No base URL configured for the GitHub connection.');
        }

        try {
            $response = $this->client($context)->get('/rate_limit');
        } catch (Throwable $exception) {
            return HealthCheckResult::unhealthy($exception->getMessage());
        }

        return $response->successful()
            ? HealthCheckResult::healthy()
            : HealthCheckResult::unhealthy("GitHub returned HTTP {$response->status()}.");
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Override]
    public function lookup(BindingContext $context, string $remoteId): ?array
    {
        $response = $this->connection($context)->get($this->repoPath($context) . "/issues/{$remoteId}");

        if ($response->status() === 404) {
            return null;
        }

        $issue = $response->json();

        return is_array($issue) && isset($issue['number']) ? $this->normalize($context, $issue)->toArray() : null;
    }

    #[Override]
    public function list(BindingContext $context, ?string $cursor = null): Page
    {
        $page = $cursor === null ? 1 : (int) $cursor;

        $response = $this->connection($context)->get($this->repoPath($context) . '/issues', [
            'state' => 'all',
            'per_page' => $this->pageSize($context),
            'page' => $page,
        ]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $response->json() ?? [];

        $items = [];

        foreach ($rows as $row) {
            // The issues endpoint also returns pull requests; they carry a
            // `pull_request` object and are not tickets.
            if (! isset($row['pull_request'])) {
                $items[] = $this->normalize($context, $row)->toArray();
            }
        }

        return new Page(
            array_values($items),
            nextCursor: $this->nextPageFromLink($response->header('Link')),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function create(BindingContext $context, array $attributes): array
    {
        $response = $this->connection($context)->post($this->repoPath($context) . '/issues', $this->toGitHubIssue($attributes));

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
        $response = $this->connection($context)->patch($this->repoPath($context) . "/issues/{$remoteId}", $this->toGitHubIssue($attributes));

        /** @var array<string, mixed> $issue */
        $issue = $response->json() ?? [];

        return $this->normalize($context, $issue)->toArray();
    }

    #[Override]
    public function comment(BindingContext $context, string $remoteId, string $body): void
    {
        $this->connection($context)->post($this->repoPath($context) . "/issues/{$remoteId}/comments", [
            'body' => $body,
        ]);
    }

    /**
     * @param  array<string, string>  $statusMap  Remote state (open/closed) → canonical category.
     */
    #[Override]
    public function translateStatus(array $statusMap, string $remoteStatus): ?string
    {
        return $statusMap[$remoteStatus] ?? null;
    }

    #[Override]
    public function commits(BindingContext $context, string $range, ?string $cursor = null): Page
    {
        $page = $cursor === null ? 1 : (int) $cursor;

        $response = $this->connection($context)->get($this->repoPath($context) . '/commits', [
            'sha' => $range,
            'per_page' => $this->pageSize($context),
            'page' => $page,
        ]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $response->json() ?? [];

        $items = array_map(fn (array $commit): array => [
            'sha' => (string) ($commit['sha'] ?? ''),
            'message' => $this->commitMessage($commit),
            'url' => isset($commit['html_url']) ? (string) $commit['html_url'] : null,
            'author' => $this->authorLogin($commit),
            'author_name' => $this->commitAuthorField($commit, 'name'),
            'author_email' => $this->commitAuthorField($commit, 'email'),
        ], $rows);

        return new Page(
            array_values($items),
            nextCursor: $this->nextPageFromLink($response->header('Link')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function compare(BindingContext $context, string $base, string $head): array
    {
        $response = $this->connection($context)->get($this->repoPath($context) . "/compare/{$base}...{$head}");

        /** @var array<string, mixed> $payload */
        return $response->json() ?? [];
    }

    #[Override]
    public function fileAtRef(BindingContext $context, string $path, string $ref): ?string
    {
        $response = $this->connection($context)->get($this->repoPath($context) . "/contents/{$path}", [
            'ref' => $ref,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $content = $response->json('content');

        if (! is_string($content)) {
            return null;
        }

        $decoded = base64_decode(str_replace("\n", '', $content), true);

        return $decoded === false ? null : $decoded;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Override]
    public function openPullRequest(BindingContext $context, array $attributes): array
    {
        $response = $this->connection($context)->post($this->repoPath($context) . '/pulls', [
            'title' => $attributes['title'] ?? null,
            'head' => $attributes['source'] ?? null,
            'base' => $attributes['target'] ?? null,
            'body' => $attributes['body'] ?? null,
        ]);

        /** @var array<string, mixed> $pull */
        $pull = $response->json() ?? [];

        return [
            'remote_id' => isset($pull['number']) ? (string) $pull['number'] : null,
            'url' => isset($pull['html_url']) ? (string) $pull['html_url'] : null,
            'raw' => $pull,
        ];
    }

    #[Override]
    public function tags(BindingContext $context, ?string $cursor = null): Page
    {
        $page = $cursor === null ? 1 : (int) $cursor;

        $response = $this->connection($context)->get($this->repoPath($context) . '/tags', [
            'per_page' => $this->pageSize($context),
            'page' => $page,
        ]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $response->json() ?? [];

        $items = array_map(fn (array $tag): array => [
            'tag' => (string) ($tag['name'] ?? ''),
            'sha' => $this->tagSha($tag),
        ], $rows);

        return new Page(
            array_values($items),
            nextCursor: $this->nextPageFromLink($response->header('Link')),
        );
    }

    /**
     * Best-effort over the first page of tags: the first tag the commit is an
     * ancestor of (comparison status `ahead` or `identical`). GitHub exposes no
     * single "tags containing commit" endpoint; recent tags are the useful set.
     */
    #[Override]
    public function firstTagContaining(BindingContext $context, string $commitSha): ?string
    {
        foreach ($this->tags($context)->items as $tag) {
            $name = (string) ($tag['tag'] ?? '');

            if ($name === '') {
                continue;
            }

            $status = $this->compare($context, $commitSha, $name)['status'] ?? null;

            if ($status === 'ahead' || $status === 'identical') {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $commit
     */
    private function commitMessage(array $commit): ?string
    {
        $inner = $commit['commit'] ?? null;

        return is_array($inner) && isset($inner['message']) ? (string) $inner['message'] : null;
    }

    /**
     * The GitHub account that authored the commit — the handle CODEOWNERS and
     * an identity map speak in. Null when the commit is not linked to an account.
     *
     * @param  array<string, mixed>  $commit
     */
    private function authorLogin(array $commit): ?string
    {
        $author = $commit['author'] ?? null;

        return is_array($author) && isset($author['login']) ? (string) $author['login'] : null;
    }

    /**
     * A field of the git author record (name/email), which is present even when
     * the commit is not linked to a GitHub account.
     *
     * @param  array<string, mixed>  $commit
     */
    private function commitAuthorField(array $commit, string $field): ?string
    {
        $inner = $commit['commit'] ?? null;
        $author = is_array($inner) ? ($inner['author'] ?? null) : null;

        return is_array($author) && isset($author[$field]) ? (string) $author[$field] : null;
    }

    /**
     * @param  array<string, mixed>  $tag
     */
    private function tagSha(array $tag): ?string
    {
        $commit = $tag['commit'] ?? null;

        return is_array($commit) && isset($commit['sha']) ? (string) $commit['sha'] : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function toGitHubIssue(array $attributes): array
    {
        $issue = [];

        if (array_key_exists('title', $attributes)) {
            $issue['title'] = $attributes['title'];
        }

        if (array_key_exists('body', $attributes)) {
            $issue['body'] = $attributes['body'];
        } elseif (array_key_exists('description', $attributes)) {
            $issue['body'] = $attributes['description'];
        }

        if (array_key_exists('state', $attributes)) {
            $issue['state'] = $attributes['state'];
        }

        return $issue;
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function normalize(BindingContext $context, array $issue): NormalizedIssue
    {
        $number = (string) ($issue['number'] ?? '');
        $repo = $context->remoteIdentifier;

        return new NormalizedIssue(
            remoteId: $number,
            title: (string) ($issue['title'] ?? ''),
            key: $repo !== null && $number !== '' ? "{$repo}#{$number}" : null,
            body: isset($issue['body']) ? (string) $issue['body'] : null,
            remoteStatus: isset($issue['state']) ? (string) $issue['state'] : null,
            remotePriority: null,
            assignee: $this->assigneeLogin($issue),
            url: isset($issue['html_url']) ? (string) $issue['html_url'] : null,
            createdAt: isset($issue['created_at']) ? (string) $issue['created_at'] : null,
            updatedAt: isset($issue['updated_at']) ? (string) $issue['updated_at'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function assigneeLogin(array $issue): ?string
    {
        $assignee = $issue['assignee'] ?? null;

        return is_array($assignee) && isset($assignee['login']) ? (string) $assignee['login'] : null;
    }

    private function nextPageFromLink(?string $link): ?string
    {
        if ($link === null) {
            return null;
        }

        if (preg_match('/<[^>]*[?&]page=(\d+)[^>]*>;\s*rel="next"/', $link, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function repoPath(BindingContext $context): string
    {
        return '/repos/' . (string) $context->remoteIdentifier;
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
            ->withToken($token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ]);
    }
}
