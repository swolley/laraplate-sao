<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Drivers\External\AzureDevOpsDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Modules\SAO\Tests\Support\Conformance\IssuesConformance;

/**
 * @return array<string, mixed>
 */
function azureWorkItem(int $id, string $title): array
{
    return [
        'id' => $id,
        'fields' => [
            'System.Title' => $title,
            'System.Description' => "body {$id}",
            'System.State' => 'Active',
            'Microsoft.VSTS.Common.Priority' => 2,
            'System.AssignedTo' => ['displayName' => 'Ada Lovelace'],
            'System.CreatedDate' => '2026-08-01T10:00:00Z',
            'System.ChangedDate' => '2026-08-02T11:00:00Z',
        ],
        '_links' => ['html' => ['href' => "https://dev.azure.com/acme/MyProject/_workitems/edit/{$id}"]],
    ];
}

function fakeAzure(): void
{
    $store = [];
    foreach (range(1, 5) as $id) {
        $store[$id] = azureWorkItem($id, "Work item {$id}");
    }
    $next = 6;

    Http::fake(function (Request $request) use (&$store, &$next) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $method = $request->method();

        if ($path === '/_apis/projects') {
            return Http::response(['value' => []]);
        }

        if (str_ends_with($path, '/_apis/wit/wiql') && $method === 'POST') {
            $ids = array_map(static fn (int $id): array => ['id' => $id], array_keys($store));

            return Http::response(['workItems' => array_values($ids)]);
        }

        if (str_ends_with($path, '/_apis/wit/workitems') && $method === 'GET') {
            $ids = array_filter(array_map('intval', explode(',', (string) ($query['ids'] ?? ''))));
            $value = array_values(array_map(static fn (int $id): array => $store[$id], array_filter($ids, static fn (int $id): bool => isset($store[$id]))));

            return Http::response(['value' => $value]);
        }

        if (str_ends_with($path, '/_apis/wit/workitems/$Issue') && $method === 'POST') {
            $ops = json_decode($request->body(), true) ?: [];
            $id = $next++;
            $store[$id] = azureWorkItem($id, azureOp($ops, '/fields/System.Title') ?? '');

            return Http::response($store[$id]);
        }

        if (preg_match('#/_apis/wit/workitems/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
            $id = (int) $m[1];

            return isset($store[$id]) ? Http::response($store[$id]) : Http::response([], 404);
        }

        if (preg_match('#/_apis/wit/workitems/(\d+)$#', $path, $m) === 1 && $method === 'PATCH') {
            $id = (int) $m[1];
            $ops = json_decode($request->body(), true) ?: [];
            $title = azureOp($ops, '/fields/System.Title');

            if (isset($store[$id]) && $title !== null) {
                $store[$id]['fields']['System.Title'] = $title;
            }

            return Http::response($store[$id] ?? ['id' => $id, 'fields' => []]);
        }

        if (preg_match('#/_apis/wit/workItems/\d+/comments$#', $path) === 1 && $method === 'POST') {
            return Http::response(['id' => 1], 200);
        }

        return Http::response(['message' => 'Unhandled'], 500);
    });
}

/**
 * @param  list<array{op: string, path: string, value: mixed}>  $ops
 */
function azureOp(array $ops, string $path): ?string
{
    foreach ($ops as $op) {
        if (($op['path'] ?? null) === $path) {
            return (string) $op['value'];
        }
    }

    return null;
}

function azureContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(baseUrl: 'https://dev.azure.com/acme', credentials: ['token' => 'pat_secret']),
        remoteIdentifier: 'MyProject',
        config: ['page_size' => 2],
        statusMap: ['Active' => 'in_progress'],
    );
}

test('the azure devops driver declares issues with pull ingest', function (): void {
    $driver = new AzureDevOpsDriver;

    expect($driver->key())->toBe('azure_devops')
        ->and($driver->capabilities())->toBe([Capability::Issues])
        ->and($driver->ingestModes())->toBe([IngestMode::Pull]);
});

test('the azure devops driver passes the issues conformance suite', function (): void {
    fakeAzure();

    IssuesConformance::assert(new AzureDevOpsDriver, azureContext());
});

test('the azure devops driver authenticates with a basic PAT', function (): void {
    fakeAzure();

    (new AzureDevOpsDriver)->list(azureContext());

    Http::assertSent(static fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic ' . base64_encode(':pat_secret')));
});

test('it normalizes an azure work item into the canonical shape', function (): void {
    fakeAzure();

    $issue = (new AzureDevOpsDriver)->lookup(azureContext(), '3');

    expect($issue['remote_id'])->toBe('3')
        ->and($issue['title'])->toBe('Work item 3')
        ->and($issue['remote_status'])->toBe('Active')
        ->and($issue['remote_priority'])->toBe('2')
        ->and($issue['assignee'])->toBe('Ada Lovelace')
        ->and($issue['url'])->toBe('https://dev.azure.com/acme/MyProject/_workitems/edit/3');
});
