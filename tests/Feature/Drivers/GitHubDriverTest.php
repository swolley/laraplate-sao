<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Drivers\External\GitHubDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Modules\SAO\Tests\Support\Conformance\BlameConformance;
use Modules\SAO\Tests\Support\Conformance\IssuesConformance;
use Modules\SAO\Tests\Support\Conformance\ReleasesConformance;
use Modules\SAO\Tests\Support\Conformance\VcsConformance;

/**
 * A network-free, stateful stand-in for the GitHub REST API. It paginates with
 * a `Link` header so the driver's cursor following is exercised.
 */
function fakeGitHub(): void
{
    $store = [
        1 => githubIssue(1, 'First', 'open'),
        2 => githubIssue(2, 'Second', 'open'),
        3 => githubIssue(3, 'Third', 'open'),
        4 => githubIssue(4, 'Fourth', 'open'),
        5 => githubIssue(5, 'Fifth', 'open'),
    ];
    $nextNumber = 6;

    Http::fake(function (Request $request) use (&$store, &$nextNumber) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $method = $request->method();

        if ($path === '/rate_limit') {
            return Http::response(['resources' => []]);
        }

        if ($path === '/graphql' && $method === 'POST') {
            return Http::response(['data' => ['repository' => ['object' => ['blame' => ['ranges' => [
                ['startingLine' => 1, 'endingLine' => 10, 'commit' => ['author' => ['name' => 'Octo Cat', 'email' => 'octo@example.com', 'user' => ['login' => 'octocat']]]],
                ['startingLine' => 11, 'endingLine' => 15, 'commit' => ['author' => ['name' => 'Ada', 'email' => 'ada@example.com', 'user' => null]]],
                ['startingLine' => 16, 'endingLine' => 20, 'commit' => ['author' => ['name' => 'Octo Cat', 'email' => 'octo@example.com', 'user' => ['login' => 'octocat']]]],
            ]]]]]]);
        }

        if ($path === '/repos/acme/widgets/issues' && $method === 'GET') {
            $page = (int) ($query['page'] ?? 1);
            $perPage = (int) ($query['per_page'] ?? 30);
            $all = array_values($store);
            $slice = array_slice($all, ($page - 1) * $perPage, $perPage);
            $hasMore = $page * $perPage < count($all);
            $headers = $hasMore
                ? ['Link' => '<https://api.github.com/repos/acme/widgets/issues?page=' . ($page + 1) . '>; rel="next"']
                : [];

            return Http::response(array_values($slice), 200, $headers);
        }

        if ($path === '/repos/acme/widgets/issues' && $method === 'POST') {
            $body = $request->data();
            $number = $nextNumber++;
            $store[$number] = githubIssue($number, (string) ($body['title'] ?? ''), 'open');

            return Http::response($store[$number], 201);
        }

        if (preg_match('#^/repos/acme/widgets/issues/(\d+)$#', $path, $m) === 1) {
            $number = (int) $m[1];

            if ($method === 'GET') {
                return isset($store[$number])
                    ? Http::response($store[$number])
                    : Http::response(['message' => 'Not Found'], 404);
            }

            if ($method === 'PATCH') {
                $body = $request->data();

                if (isset($store[$number]) && array_key_exists('title', $body)) {
                    $store[$number]['title'] = $body['title'];
                }

                return Http::response($store[$number] ?? ['number' => $number]);
            }
        }

        if (preg_match('#^/repos/acme/widgets/issues/(\d+)/comments$#', $path, $m) === 1 && $method === 'POST') {
            return Http::response(['id' => 1], 201);
        }

        if ($path === '/repos/acme/widgets/commits' && $method === 'GET') {
            $page = (int) ($query['page'] ?? 1);
            $perPage = (int) ($query['per_page'] ?? 30);
            $all = array_map(static fn (int $n): array => [
                'sha' => 'c' . $n,
                'commit' => ['message' => 'commit ' . $n, 'author' => ['name' => 'Octo Cat', 'email' => 'octo@example.com']],
                'author' => ['login' => 'octocat'],
                'html_url' => "https://github.com/acme/widgets/commit/c{$n}",
            ], range(1, 5));
            $slice = array_slice($all, ($page - 1) * $perPage, $perPage);
            $headers = $page * $perPage < count($all)
                ? ['Link' => '<https://api.github.com/repos/acme/widgets/commits?page=' . ($page + 1) . '>; rel="next"']
                : [];

            return Http::response(array_values($slice), 200, $headers);
        }

        if (str_starts_with($path, '/repos/acme/widgets/compare/')) {
            return Http::response(['status' => 'ahead', 'ahead_by' => 3, 'commits' => [], 'files' => []]);
        }

        if (str_starts_with($path, '/repos/acme/widgets/contents/')) {
            $file = mb_substr($path, mb_strlen('/repos/acme/widgets/contents/'));

            return $file === 'README.md'
                ? Http::response(['content' => base64_encode("# Widgets\n"), 'encoding' => 'base64'])
                : Http::response(['message' => 'Not Found'], 404);
        }

        if ($path === '/repos/acme/widgets/pulls' && $method === 'POST') {
            return Http::response(['number' => 42, 'html_url' => 'https://github.com/acme/widgets/pull/42'], 201);
        }

        if ($path === '/repos/acme/widgets/tags' && $method === 'GET') {
            $page = (int) ($query['page'] ?? 1);
            $perPage = (int) ($query['per_page'] ?? 30);
            $all = array_map(static fn (string $t): array => [
                'name' => $t,
                'commit' => ['sha' => 'sha-' . $t],
            ], ['v1.0.0', 'v1.1.0', 'v2.0.0']);
            $slice = array_slice($all, ($page - 1) * $perPage, $perPage);
            $headers = $page * $perPage < count($all)
                ? ['Link' => '<https://api.github.com/repos/acme/widgets/tags?page=' . ($page + 1) . '>; rel="next"']
                : [];

            return Http::response(array_values($slice), 200, $headers);
        }

        return Http::response(['message' => 'Unhandled'], 500);
    });
}

/**
 * @return array<string, mixed>
 */
function githubIssue(int $number, string $title, string $state): array
{
    return [
        'id' => 1000 + $number,
        'number' => $number,
        'title' => $title,
        'body' => 'body of #' . $number,
        'state' => $state,
        'html_url' => "https://github.com/acme/widgets/issues/{$number}",
        'assignee' => ['login' => 'octocat'],
        'created_at' => '2026-08-01T10:00:00Z',
        'updated_at' => '2026-08-02T11:00:00Z',
    ];
}

function githubContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(baseUrl: 'https://api.github.com', credentials: ['token' => 'ghp_secret']),
        remoteIdentifier: 'acme/widgets',
        config: ['page_size' => 2],
    );
}

test('the github driver declares issues, vcs and releases with pull ingest', function (): void {
    $driver = new GitHubDriver;

    expect($driver->key())->toBe('github')
        ->and($driver->capabilities())->toBe([Capability::Issues, Capability::Vcs, Capability::Releases])
        ->and($driver->ingestModes())->toBe([IngestMode::Pull]);
});

test('the github driver passes the vcs and releases conformance suites', function (): void {
    fakeGitHub();

    VcsConformance::assert(new GitHubDriver, githubContext());
    ReleasesConformance::assert(new GitHubDriver, githubContext());
});

test('the github driver passes the issues conformance suite', function (): void {
    fakeGitHub();

    IssuesConformance::assert(new GitHubDriver, githubContext());
});

test('the github driver passes the blame conformance suite', function (): void {
    fakeGitHub();

    BlameConformance::assert(new GitHubDriver, githubContext());
});

test('it aggregates github blame ranges into a per-author line tally', function (): void {
    fakeGitHub();

    $tally = collect((new GitHubDriver)->blame(githubContext(), 'app/Example.php', 'main'))
        ->keyBy(fn (array $entry): string => $entry['author'] ?? (string) $entry['author_email']);

    expect($tally['octocat']['lines'])->toBe(15)
        ->and($tally['octocat']['author_email'])->toBe('octo@example.com')
        // The range with no linked account is keyed by its git author email.
        ->and($tally['ada@example.com']['author'])->toBeNull()
        ->and($tally['ada@example.com']['lines'])->toBe(5);
});

test('the github driver authenticates with a bearer token', function (): void {
    fakeGitHub();

    (new GitHubDriver)->list(githubContext());

    Http::assertSent(static fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer ghp_secret'));
});

test('it normalizes a github commit author into the canonical shape', function (): void {
    fakeGitHub();

    $commit = (new GitHubDriver)->commits(githubContext(), 'main')->items[0];

    expect($commit['author'])->toBe('octocat')
        ->and($commit['author_name'])->toBe('Octo Cat')
        ->and($commit['author_email'])->toBe('octo@example.com');
});

test('it normalizes a github issue into the canonical shape', function (): void {
    fakeGitHub();

    $issue = (new GitHubDriver)->lookup(githubContext(), '2');

    expect($issue['remote_id'])->toBe('2')
        ->and($issue['key'])->toBe('acme/widgets#2')
        ->and($issue['title'])->toBe('Second')
        ->and($issue['remote_status'])->toBe('open')
        ->and($issue['assignee'])->toBe('octocat')
        ->and($issue['url'])->toBe('https://github.com/acme/widgets/issues/2');
});
