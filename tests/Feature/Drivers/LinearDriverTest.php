<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Drivers\External\LinearDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Modules\SAO\Tests\Support\Conformance\IssuesConformance;

/**
 * @return array<string, mixed>
 */
function linearIssue(int $n, string $title): array
{
    return [
        'id' => "id-{$n}",
        'identifier' => "ENG-{$n}",
        'title' => $title,
        'description' => "body {$n}",
        'url' => "https://linear.app/acme/issue/ENG-{$n}",
        'createdAt' => '2026-08-01T10:00:00.000Z',
        'updatedAt' => '2026-08-02T11:00:00.000Z',
        'state' => ['name' => 'Todo'],
        'priorityLabel' => 'Medium',
        'assignee' => ['name' => 'Ada'],
    ];
}

function fakeLinear(): void
{
    $store = [];
    foreach (range(1, 5) as $n) {
        $store["id-{$n}"] = linearIssue($n, "Issue {$n}");
    }
    $next = 6;

    Http::fake(function (Request $request) use (&$store, &$next) {
        if (parse_url($request->url(), PHP_URL_PATH) !== '/graphql') {
            return Http::response(['message' => 'Unhandled'], 500);
        }

        $body = $request->data();
        $query = (string) ($body['query'] ?? '');
        $vars = (array) ($body['variables'] ?? []);

        if (str_contains($query, 'viewer')) {
            return Http::response(['data' => ['viewer' => ['id' => 'me']]]);
        }

        if (str_contains($query, 'issues(')) {
            $offset = match ($vars['after'] ?? null) {
                'cur-2' => 2,
                'cur-4' => 4,
                default => 0,
            };
            $first = (int) ($vars['first'] ?? 25);
            $all = array_values($store);
            $slice = array_slice($all, $offset, $first);
            $end = $offset + count($slice);

            return Http::response(['data' => ['issues' => [
                'nodes' => $slice,
                'pageInfo' => ['hasNextPage' => $end < count($all), 'endCursor' => "cur-{$end}"],
            ]]]);
        }

        if (str_contains($query, 'issue(id:')) {
            $issue = $store[$vars['id'] ?? ''] ?? null;

            return Http::response(['data' => ['issue' => $issue]]);
        }

        if (str_contains($query, 'issueCreate')) {
            $id = "id-{$next}";
            $store[$id] = linearIssue($next, (string) ($vars['input']['title'] ?? ''));
            $store[$id]['id'] = $id;
            $next++;

            return Http::response(['data' => ['issueCreate' => ['issue' => $store[$id]]]]);
        }

        if (str_contains($query, 'issueUpdate')) {
            $id = (string) ($vars['id'] ?? '');

            if (isset($store[$id]) && isset($vars['input']['title'])) {
                $store[$id]['title'] = $vars['input']['title'];
            }

            return Http::response(['data' => ['issueUpdate' => ['issue' => $store[$id] ?? ['id' => $id]]]]);
        }

        if (str_contains($query, 'commentCreate')) {
            return Http::response(['data' => ['commentCreate' => ['success' => true]]]);
        }

        return Http::response(['errors' => [['message' => 'Unknown operation']]], 400);
    });
}

function linearContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(baseUrl: 'https://api.linear.app', credentials: ['token' => 'lin_api_secret']),
        remoteIdentifier: 'ENG',
        config: ['page_size' => 2, 'team_id' => 'team-uuid'],
        statusMap: ['Todo' => 'open'],
    );
}

test('the linear driver declares issues with pull ingest', function (): void {
    $driver = new LinearDriver;

    expect($driver->key())->toBe('linear')
        ->and($driver->capabilities())->toBe([Capability::Issues])
        ->and($driver->ingestModes())->toBe([IngestMode::Pull]);
});

test('the linear driver passes the issues conformance suite', function (): void {
    fakeLinear();

    IssuesConformance::assert(new LinearDriver, linearContext());
});

test('the linear driver authenticates with the API key header', function (): void {
    fakeLinear();

    (new LinearDriver)->list(linearContext());

    Http::assertSent(static fn (Request $request): bool => $request->hasHeader('Authorization', 'lin_api_secret'));
});

test('it normalizes a linear issue into the canonical shape', function (): void {
    fakeLinear();

    $issue = (new LinearDriver)->lookup(linearContext(), 'id-2');

    expect($issue['remote_id'])->toBe('id-2')
        ->and($issue['key'])->toBe('ENG-2')
        ->and($issue['remote_status'])->toBe('Todo')
        ->and($issue['remote_priority'])->toBe('Medium')
        ->and($issue['assignee'])->toBe('Ada')
        ->and($issue['url'])->toBe('https://linear.app/acme/issue/ENG-2');
});
