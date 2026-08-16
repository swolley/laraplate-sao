<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Drivers\External\JiraDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Modules\SAO\Tests\Support\Conformance\IssuesConformance;

/**
 * A network-free, stateful stand-in for a Jira Cloud site answering the REST v3
 * endpoints the driver calls.
 */
function fakeJira(): void
{
    $store = [
        '10001' => jiraIssue('10001', 'DEMO-1', 'First', 'To Do'),
        '10002' => jiraIssue('10002', 'DEMO-2', 'Second', 'In Progress'),
        '10003' => jiraIssue('10003', 'DEMO-3', 'Third', 'To Do'),
        '10004' => jiraIssue('10004', 'DEMO-4', 'Fourth', 'To Do'),
        '10005' => jiraIssue('10005', 'DEMO-5', 'Fifth', 'To Do'),
    ];
    $nextId = 10006;

    Http::fake(function (Request $request) use (&$store, &$nextId) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $method = $request->method();

        if ($path === '/rest/api/3/myself') {
            return Http::response(['accountId' => 'me']);
        }

        if ($path === '/rest/api/3/search' && $method === 'GET') {
            $startAt = (int) ($query['startAt'] ?? 0);
            $maxResults = (int) ($query['maxResults'] ?? 50);
            $all = array_values($store);

            return Http::response([
                'issues' => array_slice($all, $startAt, $maxResults),
                'total' => count($all),
                'startAt' => $startAt,
                'maxResults' => $maxResults,
            ]);
        }

        if ($path === '/rest/api/3/issue' && $method === 'POST') {
            $fields = $request->data()['fields'] ?? [];
            $id = (string) $nextId++;
            $key = 'DEMO-' . ($nextId - 10000);
            $store[$id] = jiraIssue($id, $key, (string) ($fields['summary'] ?? ''), 'To Do');

            return Http::response(['id' => $id, 'key' => $key], 201);
        }

        if (preg_match('#^/rest/api/3/issue/([^/]+)$#', $path, $m) === 1) {
            $id = $m[1];

            if ($method === 'GET') {
                return isset($store[$id])
                    ? Http::response($store[$id])
                    : Http::response(['errorMessages' => ['Issue does not exist']], 404);
            }

            if ($method === 'PUT') {
                $fields = $request->data()['fields'] ?? [];

                if (isset($store[$id]) && array_key_exists('summary', $fields)) {
                    $store[$id]['fields']['summary'] = $fields['summary'];
                }

                return Http::response(null, 204);
            }
        }

        if (preg_match('#^/rest/api/3/issue/([^/]+)/comment$#', $path, $m) === 1 && $method === 'POST') {
            return Http::response(['id' => '1'], 201);
        }

        return Http::response(['errorMessages' => ['Unhandled']], 500);
    });
}

/**
 * @return array<string, mixed>
 */
function jiraIssue(string $id, string $key, string $summary, string $status): array
{
    return [
        'id' => $id,
        'key' => $key,
        'fields' => [
            'summary' => $summary,
            'description' => 'body of ' . $key,
            'status' => ['name' => $status],
            'priority' => ['name' => 'Medium'],
            'assignee' => ['displayName' => 'Jane Dev'],
            'created' => '2026-08-01T10:00:00.000+0000',
            'updated' => '2026-08-02T11:00:00.000+0000',
        ],
    ];
}

function jiraContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(
            baseUrl: 'https://acme.atlassian.net',
            credentials: ['email' => 'bot@acme.test', 'token' => 'secret-token'],
        ),
        remoteIdentifier: 'DEMO',
        config: ['page_size' => 2],
    );
}

test('the jira driver declares the issues capability and pull ingest', function (): void {
    $driver = new JiraDriver;

    expect($driver->key())->toBe('jira')
        ->and($driver->capabilities())->toBe([Capability::Issues])
        ->and($driver->ingestModes())->toBe([IngestMode::Pull]);
});

test('the jira driver passes the issues conformance suite', function (): void {
    fakeJira();

    IssuesConformance::assert(new JiraDriver, jiraContext());
});

test('the jira driver authenticates with basic auth', function (): void {
    fakeJira();

    (new JiraDriver)->list(jiraContext());

    Http::assertSent(static function (Request $request): bool {
        $expected = 'Basic ' . base64_encode('bot@acme.test:secret-token');

        return $request->hasHeader('Authorization', $expected);
    });
});

test('it normalizes a jira issue into the canonical shape', function (): void {
    fakeJira();

    $issue = (new JiraDriver)->lookup(jiraContext(), '10002');

    expect($issue['remote_id'])->toBe('10002')
        ->and($issue['key'])->toBe('DEMO-2')
        ->and($issue['title'])->toBe('Second')
        ->and($issue['remote_status'])->toBe('In Progress')
        ->and($issue['remote_priority'])->toBe('Medium')
        ->and($issue['assignee'])->toBe('Jane Dev')
        ->and($issue['url'])->toBe('https://acme.atlassian.net/browse/DEMO-2');
});

test('a health check hits the myself endpoint', function (): void {
    fakeJira();

    $result = (new JiraDriver)->healthCheck(jiraContext()->connection);

    expect($result->healthy)->toBeTrue();
    Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/rest/api/3/myself'));
});
