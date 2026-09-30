<?php

declare(strict_types=1);

namespace Modules\SAO\Tests\Support\Drivers;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * A network-free, stateful stand-in for a Redmine installation, installed via
 * `Http::fake()`. It holds issues in memory, answers the REST endpoints
 * {@see \Modules\SAO\Drivers\External\RedmineDriver} calls, and counts writes so
 * end-to-end sync tests can prove a push is idempotent on the remote side.
 */
final class FakeRedmineApi
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $issues = [];

    public int $createCount = 0;

    public int $updateCount = 0;

    private int $nextId = 1;

    public static function install(): self
    {
        $api = new self;

        Http::fake(static fn (Request $request): PromiseInterface|Response => $api->handle($request));

        return $api;
    }

    /**
     * @return array<string, mixed>
     */
    public static function issue(int $id, string $subject, string $status, string $description = ''): array
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

    public function seed(string $subject, string $status, string $description = ''): int
    {
        $id = $this->nextId++;
        $this->issues[$id] = self::issue($id, $subject, $status, $description);

        return $id;
    }

    private function handle(Request $request): PromiseInterface|Response
    {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        $method = $request->method();

        if ($path === '/projects.json') {
            return Http::response(['projects' => [], 'total_count' => 0]);
        }

        if ($path === '/issues.json' && $method === 'GET') {
            $all = array_values($this->issues);

            return Http::response(['issues' => $all, 'total_count' => count($all), 'offset' => 0, 'limit' => count($all)]);
        }

        if ($path === '/issues.json' && $method === 'POST') {
            /** @var array<string, mixed> $attributes */
            $attributes = $request->data()['issue'] ?? [];
            $this->createCount++;
            $id = $this->seed((string) ($attributes['subject'] ?? ''), 'New', (string) ($attributes['description'] ?? ''));

            return Http::response(['issue' => $this->issues[$id]], 201);
        }

        if (preg_match('#^/issues/(\d+)\.json$#', (string) $path, $matches) === 1) {
            $id = (int) $matches[1];

            if ($method === 'GET') {
                return isset($this->issues[$id])
                    ? Http::response(['issue' => $this->issues[$id]])
                    : Http::response(['errors' => ['Not found']], 404);
            }

            if ($method === 'PUT' && isset($this->issues[$id])) {
                /** @var array<string, mixed> $attributes */
                $attributes = $request->data()['issue'] ?? [];
                $this->updateCount++;

                if (array_key_exists('subject', $attributes)) {
                    $this->issues[$id]['subject'] = $attributes['subject'];
                }

                if (array_key_exists('description', $attributes)) {
                    $this->issues[$id]['description'] = $attributes['description'];
                }

                return Http::response(null, 204);
            }
        }

        return Http::response(['errors' => ['Unhandled']], 500);
    }
}
