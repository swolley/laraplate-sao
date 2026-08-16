<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Drivers\External\BitbucketDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Modules\SAO\Tests\Support\Conformance\IssuesConformance;
use Modules\SAO\Tests\Support\Conformance\ReleasesConformance;
use Modules\SAO\Tests\Support\Conformance\VcsConformance;

/**
 * A network-free, stateful stand-in for the Bitbucket Cloud REST 2.0 API. The
 * list responses carry a `next` link, which is how Bitbucket signals more pages.
 */
function fakeBitbucket(): void
{
    $store = [
        1 => bitbucketIssue(1, 'First', 'new'),
        2 => bitbucketIssue(2, 'Second', 'open'),
        3 => bitbucketIssue(3, 'Third', 'new'),
        4 => bitbucketIssue(4, 'Fourth', 'new'),
        5 => bitbucketIssue(5, 'Fifth', 'new'),
    ];
    $nextId = 6;

    Http::fake(function (Request $request) use (&$store, &$nextId) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $method = $request->method();

        if ($path === '/2.0/user') {
            return Http::response(['account_id' => 'me']);
        }

        if ($path === '/2.0/repositories/acme/widgets/issues' && $method === 'GET') {
            $page = (int) ($query['page'] ?? 1);
            $pagelen = (int) ($query['pagelen'] ?? 10);
            $all = array_values($store);
            $slice = array_slice($all, ($page - 1) * $pagelen, $pagelen);
            $hasMore = $page * $pagelen < count($all);
            $payload = ['values' => array_values($slice), 'size' => count($all), 'page' => $page, 'pagelen' => $pagelen];

            if ($hasMore) {
                $payload['next'] = 'https://api.bitbucket.org/2.0/repositories/acme/widgets/issues?page=' . ($page + 1);
            }

            return Http::response($payload);
        }

        if ($path === '/2.0/repositories/acme/widgets/issues' && $method === 'POST') {
            $body = $request->data();
            $id = $nextId++;
            $store[$id] = bitbucketIssue($id, (string) ($body['title'] ?? ''), 'new');

            return Http::response($store[$id], 201);
        }

        if (preg_match('#^/2.0/repositories/acme/widgets/issues/(\d+)$#', $path, $m) === 1) {
            $id = (int) $m[1];

            if ($method === 'GET') {
                return isset($store[$id])
                    ? Http::response($store[$id])
                    : Http::response(['type' => 'error'], 404);
            }

            if ($method === 'PUT') {
                $body = $request->data();

                if (isset($store[$id]) && array_key_exists('title', $body)) {
                    $store[$id]['title'] = $body['title'];
                }

                return Http::response($store[$id] ?? ['id' => $id]);
            }
        }

        if (preg_match('#^/2.0/repositories/acme/widgets/issues/(\d+)/comments$#', $path, $m) === 1 && $method === 'POST') {
            return Http::response(['id' => 1], 201);
        }

        if (str_starts_with($path, '/2.0/repositories/acme/widgets/commits/') && $method === 'GET') {
            $page = (int) ($query['page'] ?? 1);
            $pagelen = (int) ($query['pagelen'] ?? 10);
            $all = array_map(static fn (int $n): array => [
                'hash' => 'c' . $n,
                'message' => 'commit ' . $n,
                'links' => ['html' => ['href' => "https://bitbucket.org/acme/widgets/commits/c{$n}"]],
            ], range(1, 5));
            $slice = array_slice($all, ($page - 1) * $pagelen, $pagelen);
            $payload = ['values' => array_values($slice), 'pagelen' => $pagelen, 'page' => $page];

            if ($page * $pagelen < count($all)) {
                $payload['next'] = 'https://api.bitbucket.org/2.0/repositories/acme/widgets/commits/main?page=' . ($page + 1);
            }

            return Http::response($payload);
        }

        if (str_starts_with($path, '/2.0/repositories/acme/widgets/diffstat/')) {
            return Http::response(['values' => [['status' => 'modified']], 'size' => 1]);
        }

        if (str_starts_with($path, '/2.0/repositories/acme/widgets/src/')) {
            $rest = mb_substr($path, mb_strlen('/2.0/repositories/acme/widgets/src/'));
            $file = mb_substr($rest, (int) mb_strpos($rest, '/') + 1);

            return $file === 'README.md'
                ? Http::response("# Widgets\n")
                : Http::response(['type' => 'error'], 404);
        }

        if ($path === '/2.0/repositories/acme/widgets/pullrequests' && $method === 'POST') {
            return Http::response(['id' => 9, 'links' => ['html' => ['href' => 'https://bitbucket.org/acme/widgets/pull-requests/9']]], 201);
        }

        if ($path === '/2.0/repositories/acme/widgets/refs/tags' && $method === 'GET') {
            $page = (int) ($query['page'] ?? 1);
            $pagelen = (int) ($query['pagelen'] ?? 10);
            $all = array_map(static fn (string $t): array => [
                'name' => $t,
                'target' => ['hash' => 'sha-' . $t],
            ], ['v1.0.0', 'v1.1.0', 'v2.0.0']);
            $slice = array_slice($all, ($page - 1) * $pagelen, $pagelen);
            $payload = ['values' => array_values($slice), 'pagelen' => $pagelen, 'page' => $page];

            if ($page * $pagelen < count($all)) {
                $payload['next'] = 'https://api.bitbucket.org/2.0/repositories/acme/widgets/refs/tags?page=' . ($page + 1);
            }

            return Http::response($payload);
        }

        return Http::response(['type' => 'error'], 500);
    });
}

/**
 * @return array<string, mixed>
 */
function bitbucketIssue(int $id, string $title, string $state): array
{
    return [
        'id' => $id,
        'title' => $title,
        'content' => ['raw' => 'body of #' . $id],
        'state' => $state,
        'priority' => 'major',
        'assignee' => ['display_name' => 'Bit Bucket'],
        'created_on' => '2026-08-01T10:00:00Z',
        'updated_on' => '2026-08-02T11:00:00Z',
        'links' => ['html' => ['href' => "https://bitbucket.org/acme/widgets/issues/{$id}"]],
    ];
}

function bitbucketContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(
            baseUrl: 'https://api.bitbucket.org/2.0',
            credentials: ['username' => 'bot', 'token' => 'app-password'],
        ),
        remoteIdentifier: 'acme/widgets',
        config: ['page_size' => 2],
    );
}

test('the bitbucket driver declares issues, vcs and releases with pull ingest', function (): void {
    $driver = new BitbucketDriver;

    expect($driver->key())->toBe('bitbucket')
        ->and($driver->capabilities())->toBe([Capability::Issues, Capability::Vcs, Capability::Releases])
        ->and($driver->ingestModes())->toBe([IngestMode::Pull]);
});

test('the bitbucket driver passes the vcs and releases conformance suites', function (): void {
    fakeBitbucket();

    VcsConformance::assert(new BitbucketDriver, bitbucketContext());
    ReleasesConformance::assert(new BitbucketDriver, bitbucketContext());
});

test('the bitbucket driver passes the issues conformance suite', function (): void {
    fakeBitbucket();

    IssuesConformance::assert(new BitbucketDriver, bitbucketContext());
});

test('the bitbucket driver authenticates with basic auth', function (): void {
    fakeBitbucket();

    (new BitbucketDriver)->list(bitbucketContext());

    Http::assertSent(static function (Request $request): bool {
        $expected = 'Basic ' . base64_encode('bot:app-password');

        return $request->hasHeader('Authorization', $expected);
    });
});

test('it normalizes a bitbucket issue into the canonical shape', function (): void {
    fakeBitbucket();

    $issue = (new BitbucketDriver)->lookup(bitbucketContext(), '2');

    expect($issue['remote_id'])->toBe('2')
        ->and($issue['key'])->toBe('acme/widgets#2')
        ->and($issue['title'])->toBe('Second')
        ->and($issue['body'])->toBe('body of #2')
        ->and($issue['remote_status'])->toBe('open')
        ->and($issue['remote_priority'])->toBe('major')
        ->and($issue['assignee'])->toBe('Bit Bucket')
        ->and($issue['url'])->toBe('https://bitbucket.org/acme/widgets/issues/2');
});
