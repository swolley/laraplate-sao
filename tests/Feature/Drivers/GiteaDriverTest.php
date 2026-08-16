<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Drivers\External\GiteaDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Modules\SAO\Tests\Support\Conformance\IssuesConformance;

/**
 * A network-free, stateful stand-in for the Gitea REST API. It paginates with a
 * `Link` header so the driver's cursor following is exercised, and returns pull
 * requests among issues so they must be filtered out.
 */
function fakeGitea(): void
{
    $store = [
        1 => giteaIssue(1, 'First', 'open'),
        2 => giteaIssue(2, 'Second', 'open'),
        3 => giteaIssue(3, 'Third', 'closed'),
        4 => giteaIssue(4, 'Fourth', 'open'),
        5 => giteaIssue(5, 'Fifth', 'open'),
    ];
    $nextNumber = 6;

    Http::fake(function (Request $request) use (&$store, &$nextNumber) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $method = $request->method();

        if ($path === '/api/v1/version') {
            return Http::response(['version' => '1.22.0']);
        }

        if ($path === '/api/v1/repos/acme/widgets/issues' && $method === 'GET') {
            $page = (int) ($query['page'] ?? 1);
            $limit = (int) ($query['limit'] ?? 30);
            $all = array_values($store);
            // A pull request leaks into the issues feed and must be filtered.
            $all[] = ['number' => 99, 'title' => 'A PR', 'state' => 'open', 'pull_request' => ['merged' => false]];
            $slice = array_slice($all, ($page - 1) * $limit, $limit);
            $hasMore = $page * $limit < count($all);
            $headers = $hasMore
                ? ['Link' => '<https://gitea.example.com/api/v1/repos/acme/widgets/issues?page=' . ($page + 1) . '>; rel="next"']
                : [];

            return Http::response(array_values($slice), 200, $headers);
        }

        if ($path === '/api/v1/repos/acme/widgets/issues' && $method === 'POST') {
            $body = $request->data();
            $number = $nextNumber++;
            $store[$number] = giteaIssue($number, (string) ($body['title'] ?? ''), 'open');

            return Http::response($store[$number], 201);
        }

        if (preg_match('#^/api/v1/repos/acme/widgets/issues/(\d+)$#', $path, $m) === 1) {
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

        if (preg_match('#^/api/v1/repos/acme/widgets/issues/(\d+)/comments$#', $path, $m) === 1 && $method === 'POST') {
            return Http::response(['id' => 1], 201);
        }

        return Http::response(['message' => 'Unhandled'], 500);
    });
}

/**
 * @return array<string, mixed>
 */
function giteaIssue(int $number, string $title, string $state): array
{
    return [
        'id' => 2000 + $number,
        'number' => $number,
        'title' => $title,
        'body' => 'body of #' . $number,
        'state' => $state,
        'html_url' => "https://gitea.example.com/acme/widgets/issues/{$number}",
        'assignee' => ['login' => 'giteabot'],
        'created_at' => '2026-08-01T10:00:00Z',
        'updated_at' => '2026-08-02T11:00:00Z',
    ];
}

function giteaContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(baseUrl: 'https://gitea.example.com/api/v1', credentials: ['token' => 'gitea_secret']),
        remoteIdentifier: 'acme/widgets',
        config: ['page_size' => 2],
    );
}

test('the gitea driver declares issues with pull ingest', function (): void {
    $driver = new GiteaDriver;

    expect($driver->key())->toBe('gitea')
        ->and($driver->capabilities())->toBe([Capability::Issues])
        ->and($driver->ingestModes())->toBe([IngestMode::Pull]);
});

test('the gitea driver passes the issues conformance suite', function (): void {
    fakeGitea();

    IssuesConformance::assert(new GiteaDriver, giteaContext());
});

test('the gitea driver authenticates with a token header and filters pull requests', function (): void {
    fakeGitea();

    $page = (new GiteaDriver)->list(giteaContext());

    expect(collect($page->items)->pluck('remote_id'))->not->toContain('99');

    Http::assertSent(static fn (Request $request): bool => $request->hasHeader('Authorization', 'token gitea_secret'));
});

test('it normalizes a gitea issue into the canonical shape', function (): void {
    fakeGitea();

    $issue = (new GiteaDriver)->lookup(giteaContext(), '2');

    expect($issue['remote_id'])->toBe('2')
        ->and($issue['key'])->toBe('acme/widgets#2')
        ->and($issue['title'])->toBe('Second')
        ->and($issue['remote_status'])->toBe('open')
        ->and($issue['assignee'])->toBe('giteabot')
        ->and($issue['url'])->toBe('https://gitea.example.com/acme/widgets/issues/2');
});
