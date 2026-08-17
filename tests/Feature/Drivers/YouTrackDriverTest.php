<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Drivers\External\YouTrackDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Modules\SAO\Tests\Support\Conformance\IssuesConformance;

/**
 * @return array<string, mixed>
 */
function youtrackIssue(int $n, string $summary): array
{
    return [
        'idReadable' => "PRJ-{$n}",
        'summary' => $summary,
        'description' => "body {$n}",
        'created' => 1_700_000_000_000 + $n,
        'updated' => 1_700_000_001_000 + $n,
        'customFields' => [
            ['name' => 'State', 'value' => ['name' => 'Open']],
            ['name' => 'Priority', 'value' => ['name' => 'Normal']],
            ['name' => 'Assignee', 'value' => ['login' => 'ada', 'name' => 'Ada']],
        ],
    ];
}

function fakeYouTrack(): void
{
    $store = [];
    foreach (range(1, 5) as $n) {
        $store["PRJ-{$n}"] = youtrackIssue($n, "Issue {$n}");
    }
    $next = 6;

    Http::fake(function (Request $request) use (&$store, &$next) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $method = $request->method();

        if ($path === '/api/users/me') {
            return Http::response(['login' => 'me']);
        }

        if ($path === '/api/issues' && $method === 'GET') {
            $skip = (int) ($query['$skip'] ?? 0);
            $top = (int) ($query['$top'] ?? 25);

            return Http::response(array_slice(array_values($store), $skip, $top));
        }

        if ($path === '/api/issues' && $method === 'POST') {
            $body = $request->data();
            $id = "PRJ-{$next}";
            $next++;
            $store[$id] = youtrackIssue((int) filter_var($id, FILTER_SANITIZE_NUMBER_INT), (string) ($body['summary'] ?? ''));
            $store[$id]['idReadable'] = $id;

            return Http::response($store[$id]);
        }

        if (preg_match('#^/api/issues/(PRJ-\d+)$#', $path, $m) === 1 && $method === 'GET') {
            return isset($store[$m[1]]) ? Http::response($store[$m[1]]) : Http::response([], 404);
        }

        if (preg_match('#^/api/issues/(PRJ-\d+)$#', $path, $m) === 1 && $method === 'POST') {
            $body = $request->data();

            if (isset($store[$m[1]]) && array_key_exists('summary', $body)) {
                $store[$m[1]]['summary'] = $body['summary'];
            }

            return Http::response($store[$m[1]] ?? ['idReadable' => $m[1]]);
        }

        if (preg_match('#^/api/issues/PRJ-\d+/comments$#', $path) === 1 && $method === 'POST') {
            return Http::response(['id' => 'c-1'], 200);
        }

        return Http::response(['message' => 'Unhandled'], 500);
    });
}

function youtrackContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(baseUrl: 'https://youtrack.example.com', credentials: ['token' => 'perm:secret']),
        remoteIdentifier: 'PRJ',
        config: ['page_size' => 2],
        statusMap: ['Open' => 'open'],
    );
}

test('the youtrack driver declares issues with pull ingest', function (): void {
    $driver = new YouTrackDriver;

    expect($driver->key())->toBe('youtrack')
        ->and($driver->capabilities())->toBe([Capability::Issues])
        ->and($driver->ingestModes())->toBe([IngestMode::Pull]);
});

test('the youtrack driver passes the issues conformance suite', function (): void {
    fakeYouTrack();

    IssuesConformance::assert(new YouTrackDriver, youtrackContext());
});

test('the youtrack driver authenticates with a bearer token', function (): void {
    fakeYouTrack();

    (new YouTrackDriver)->list(youtrackContext());

    Http::assertSent(static fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer perm:secret'));
});

test('it normalizes a youtrack issue into the canonical shape', function (): void {
    fakeYouTrack();

    $issue = (new YouTrackDriver)->lookup(youtrackContext(), 'PRJ-2');

    expect($issue['remote_id'])->toBe('PRJ-2')
        ->and($issue['key'])->toBe('PRJ-2')
        ->and($issue['remote_status'])->toBe('Open')
        ->and($issue['remote_priority'])->toBe('Normal')
        ->and($issue['assignee'])->toBe('Ada')
        ->and($issue['url'])->toBe('https://youtrack.example.com/issue/PRJ-2');
});
