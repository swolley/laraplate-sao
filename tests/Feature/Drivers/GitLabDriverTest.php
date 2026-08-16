<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Drivers\External\GitLabDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Modules\SAO\Tests\Support\Conformance\IssuesConformance;

/**
 * A network-free, stateful stand-in for the GitLab REST v4 API. Pagination is
 * signalled with the `X-Next-Page` response header, as GitLab does.
 */
function fakeGitLab(): void
{
    $store = [
        1 => gitlabIssue(1, 'First', 'opened'),
        2 => gitlabIssue(2, 'Second', 'opened'),
        3 => gitlabIssue(3, 'Third', 'opened'),
        4 => gitlabIssue(4, 'Fourth', 'opened'),
        5 => gitlabIssue(5, 'Fifth', 'opened'),
    ];
    $nextIid = 6;

    Http::fake(function (Request $request) use (&$store, &$nextIid) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $method = $request->method();

        if ($path === '/api/v4/version') {
            return Http::response(['version' => '17.0.0']);
        }

        if ($path === '/api/v4/projects/42/issues' && $method === 'GET') {
            $page = (int) ($query['page'] ?? 1);
            $perPage = (int) ($query['per_page'] ?? 20);
            $all = array_values($store);
            $slice = array_slice($all, ($page - 1) * $perPage, $perPage);
            $hasMore = $page * $perPage < count($all);

            return Http::response(array_values($slice), 200, [
                'X-Next-Page' => $hasMore ? (string) ($page + 1) : '',
                'X-Total' => (string) count($all),
            ]);
        }

        if ($path === '/api/v4/projects/42/issues' && $method === 'POST') {
            $body = $request->data();
            $iid = $nextIid++;
            $store[$iid] = gitlabIssue($iid, (string) ($body['title'] ?? ''), 'opened');

            return Http::response($store[$iid], 201);
        }

        if (preg_match('#^/api/v4/projects/42/issues/(\d+)$#', $path, $m) === 1) {
            $iid = (int) $m[1];

            if ($method === 'GET') {
                return isset($store[$iid])
                    ? Http::response($store[$iid])
                    : Http::response(['message' => '404 Not found'], 404);
            }

            if ($method === 'PUT') {
                $body = $request->data();

                if (isset($store[$iid]) && array_key_exists('title', $body)) {
                    $store[$iid]['title'] = $body['title'];
                }

                return Http::response($store[$iid] ?? ['iid' => $iid]);
            }
        }

        if (preg_match('#^/api/v4/projects/42/issues/(\d+)/notes$#', $path, $m) === 1 && $method === 'POST') {
            return Http::response(['id' => 1], 201);
        }

        return Http::response(['message' => 'Unhandled'], 500);
    });
}

/**
 * @return array<string, mixed>
 */
function gitlabIssue(int $iid, string $title, string $state): array
{
    return [
        'id' => 9000 + $iid,
        'iid' => $iid,
        'title' => $title,
        'description' => 'body of #' . $iid,
        'state' => $state,
        'web_url' => "https://gitlab.com/acme/widgets/-/issues/{$iid}",
        'assignee' => ['username' => 'gluser'],
        'created_at' => '2026-08-01T10:00:00Z',
        'updated_at' => '2026-08-02T11:00:00Z',
    ];
}

function gitlabContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(baseUrl: 'https://gitlab.com', credentials: ['token' => 'glpat-secret']),
        remoteIdentifier: '42',
        config: ['page_size' => 2],
    );
}

test('the gitlab driver declares the issues capability and pull ingest', function (): void {
    $driver = new GitLabDriver;

    expect($driver->key())->toBe('gitlab')
        ->and($driver->capabilities())->toBe([Capability::Issues])
        ->and($driver->ingestModes())->toBe([IngestMode::Pull]);
});

test('the gitlab driver passes the issues conformance suite', function (): void {
    fakeGitLab();

    IssuesConformance::assert(new GitLabDriver, gitlabContext());
});

test('the gitlab driver authenticates with the PRIVATE-TOKEN header', function (): void {
    fakeGitLab();

    (new GitLabDriver)->list(gitlabContext());

    Http::assertSent(static fn (Request $request): bool => $request->hasHeader('PRIVATE-TOKEN', 'glpat-secret'));
});

test('it normalizes a gitlab issue into the canonical shape', function (): void {
    fakeGitLab();

    $issue = (new GitLabDriver)->lookup(gitlabContext(), '2');

    expect($issue['remote_id'])->toBe('2')
        ->and($issue['key'])->toBe('42#2')
        ->and($issue['title'])->toBe('Second')
        ->and($issue['remote_status'])->toBe('opened')
        ->and($issue['assignee'])->toBe('gluser')
        ->and($issue['url'])->toBe('https://gitlab.com/acme/widgets/-/issues/2');
});
