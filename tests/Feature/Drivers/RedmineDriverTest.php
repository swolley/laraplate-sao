<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Drivers\External\RedmineDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Modules\SAO\Tests\Support\Conformance\IssuesConformance;

/**
 * A network-free, stateful stand-in for a Redmine installation. It holds issues
 * in memory and answers the REST endpoints the driver calls, so the driver is
 * exercised against the documented API shape without a live server.
 */
function fakeRedmine(): void
{
    $store = [
        1 => redmineIssue(1, 'First', 'New'),
        2 => redmineIssue(2, 'Second', 'In Progress'),
        3 => redmineIssue(3, 'Third', 'New'),
        4 => redmineIssue(4, 'Fourth', 'New'),
        5 => redmineIssue(5, 'Fifth', 'New'),
    ];
    $nextId = 6;

    Http::fake(function (Request $request) use (&$store, &$nextId): Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $method = $request->method();

        if ($path === '/projects.json') {
            return Http::response(['projects' => [], 'total_count' => 0]);
        }

        if ($path === '/issues.json' && $method === 'GET') {
            $offset = (int) ($query['offset'] ?? 0);
            $limit = (int) ($query['limit'] ?? 25);
            $all = array_values($store);
            $slice = array_slice($all, $offset, $limit);

            return Http::response(['issues' => $slice, 'total_count' => count($all), 'offset' => $offset, 'limit' => $limit]);
        }

        if ($path === '/issues.json' && $method === 'POST') {
            $attrs = $request->data()['issue'] ?? [];
            $id = $nextId++;
            $store[$id] = redmineIssue($id, (string) ($attrs['subject'] ?? ''), 'New', (string) ($attrs['description'] ?? ''));

            return Http::response(['issue' => $store[$id]], 201);
        }

        if (preg_match('#^/issues/(\d+)\.json$#', $path, $m) === 1) {
            $id = (int) $m[1];

            if ($method === 'GET') {
                return isset($store[$id])
                    ? Http::response(['issue' => $store[$id]])
                    : Http::response(['errors' => ['Not found']], 404);
            }

            if ($method === 'PUT') {
                $attrs = $request->data()['issue'] ?? [];

                if (isset($store[$id])) {
                    if (array_key_exists('subject', $attrs)) {
                        $store[$id]['subject'] = $attrs['subject'];
                    }

                    if (array_key_exists('description', $attrs)) {
                        $store[$id]['description'] = $attrs['description'];
                    }
                }

                return Http::response(null, 204);
            }
        }

        return Http::response(['errors' => ['Unhandled']], 500);
    });
}

/**
 * @return array<string, mixed>
 */
function redmineIssue(int $id, string $subject, string $status, string $description = ''): array
{
    return [
        'id' => $id,
        'subject' => $subject,
        'description' => $description,
        'status' => ['id' => 1, 'name' => $status],
        'priority' => ['id' => 2, 'name' => 'Normal'],
        'assigned_to' => ['id' => 7, 'name' => 'Jane Dev'],
        'created_on' => '2026-08-01T10:00:00Z',
        'updated_on' => '2026-08-02T11:00:00Z',
    ];
}

function redmineContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(baseUrl: 'https://redmine.example.test', credentials: ['token' => 'secret-key']),
        remoteIdentifier: 'demo-project',
        config: ['page_size' => 2],
    );
}

test('the redmine driver declares the issues capability and pull ingest', function (): void {
    $driver = new RedmineDriver;

    expect($driver->key())->toBe('redmine')
        ->and($driver->capabilities())->toBe([Capability::Issues])
        ->and($driver->ingestModes())->toBe([IngestMode::Pull])
        ->and($driver->configurationSchema()->fields[0]->name)->toBe('token');
});

test('the redmine driver passes the issues conformance suite', function (): void {
    fakeRedmine();

    IssuesConformance::assert(new RedmineDriver, redmineContext());
});

test('the redmine driver authenticates with the API-key header', function (): void {
    fakeRedmine();

    (new RedmineDriver)->list(redmineContext());

    Http::assertSent(static fn (Request $request): bool => $request->hasHeader('X-Redmine-API-Key', 'secret-key'));
});

test('a health check hits the projects endpoint', function (): void {
    fakeRedmine();

    $result = (new RedmineDriver)->healthCheck(redmineContext()->connection);

    expect($result->healthy)->toBeTrue();
    Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/projects.json'));
});

test('a missing base URL is reported unhealthy without a request', function (): void {
    Http::fake();

    $result = (new RedmineDriver)->healthCheck(new ConnectionContext(baseUrl: null, credentials: ['token' => 'x']));

    expect($result->healthy)->toBeFalse();
    Http::assertNothingSent();
});

test('it normalizes a redmine issue into the canonical shape', function (): void {
    fakeRedmine();

    $issue = (new RedmineDriver)->lookup(redmineContext(), '2');

    expect($issue['remote_id'])->toBe('2')
        ->and($issue['title'])->toBe('Second')
        ->and($issue['remote_status'])->toBe('In Progress')
        ->and($issue['remote_priority'])->toBe('Normal')
        ->and($issue['assignee'])->toBe('Jane Dev')
        ->and($issue['url'])->toBe('https://redmine.example.test/issues/2');
});
