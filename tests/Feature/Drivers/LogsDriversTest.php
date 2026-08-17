<?php

declare(strict_types=1);

use Modules\SAO\Drivers\Contracts\LogsCapability;
use Modules\SAO\Drivers\External\BetterStackDriver;
use Modules\SAO\Drivers\External\BugsnagDriver;
use Modules\SAO\Drivers\External\DatadogDriver;
use Modules\SAO\Drivers\External\ElasticDriver;
use Modules\SAO\Drivers\External\GlitchTipDriver;
use Modules\SAO\Drivers\External\GrafanaDriver;
use Modules\SAO\Drivers\External\HoneybadgerDriver;
use Modules\SAO\Drivers\External\RollbarDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Modules\SAO\Ingest\GroupKeyResolver;

function logsContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(baseUrl: null, credentials: ['secret' => 'shared']),
        remoteIdentifier: 'acme',
    );
}

/**
 * @return array{0: LogsCapability&Modules\SAO\Drivers\Contracts\DriverInterface, 1: string, 2: string, 3: bool, 4: string}
 *                                                                                                                          Each row: driver, token header, payload JSON, expected native (true) or computed (false), expected message.
 */
dataset('logs drivers', [
    'glitchtip' => [
        fn () => new GlitchTipDriver,
        'X-GlitchTip-Token',
        fn () => json_encode(['data' => ['issue' => ['id' => 'i-1', 'title' => 'TypeError x', 'level' => 'error']]], JSON_THROW_ON_ERROR),
        true,
        'TypeError x',
        'glitchtip:i-1',
    ],
    'rollbar' => [
        fn () => new RollbarDriver,
        'X-Rollbar-Token',
        fn () => json_encode(['data' => ['item' => ['counter' => 42, 'title' => 'undefined method', 'level' => 'error']]], JSON_THROW_ON_ERROR),
        true,
        'undefined method',
        'rollbar:42',
    ],
    'bugsnag' => [
        fn () => new BugsnagDriver,
        'X-Bugsnag-Token',
        fn () => json_encode(['error' => ['errorId' => 'e-9', 'message' => 'boom', 'exceptionClass' => 'RuntimeException']], JSON_THROW_ON_ERROR),
        true,
        'boom',
        'bugsnag:e-9',
    ],
    'honeybadger' => [
        fn () => new HoneybadgerDriver,
        'X-Honeybadger-Token',
        fn () => json_encode(['fault' => ['id' => 7, 'message' => 'nil', 'klass' => 'NoMethodError']], JSON_THROW_ON_ERROR),
        true,
        'nil',
        'honeybadger:7',
    ],
    'grafana' => [
        fn () => new GrafanaDriver,
        'X-Grafana-Token',
        fn () => json_encode(['alerts' => [['labels' => ['alertname' => 'HighErr', 'severity' => 'critical'], 'annotations' => ['summary' => 'Error rate high']]]], JSON_THROW_ON_ERROR),
        false,
        'Error rate high',
        null,
    ],
    'datadog' => [
        fn () => new DatadogDriver,
        'X-Datadog-Token',
        fn () => json_encode(['title' => 'CPU alert', 'body' => 'host down', 'alert_type' => 'error'], JSON_THROW_ON_ERROR),
        false,
        'CPU alert',
        null,
    ],
    'elastic' => [
        fn () => new ElasticDriver,
        'X-Elastic-Token',
        fn () => json_encode(['message' => 'watcher fired', 'level' => 'warning'], JSON_THROW_ON_ERROR),
        false,
        'watcher fired',
        null,
    ],
    'betterstack' => [
        fn () => new BetterStackDriver,
        'X-BetterStack-Token',
        fn () => json_encode(['data' => ['attributes' => ['name' => 'Site down', 'started_at' => '2026-08-16T10:00:00Z']]], JSON_THROW_ON_ERROR),
        false,
        'Site down',
        null,
    ],
]);

test('the driver declares logs with push ingest and its native-key stance', function (callable $make, string $header, callable $payload, bool $native): void {
    $driver = $make();

    expect($driver->capabilities())->toBe([Capability::Logs])
        ->and($driver->ingestModes())->toBe([IngestMode::Push])
        ->and($driver->carriesNativeGroupKey())->toBe($native);
})->with('logs drivers');

test('a delivery with the shared token verifies, a wrong or missing one does not', function (callable $make, string $header, callable $payload): void {
    $driver = $make();
    $body = $payload();

    expect($driver->verifySignature(logsContext(), $body, [$header => 'shared']))->toBeTrue()
        ->and($driver->verifySignature(logsContext(), $body, [$header => 'nope']))->toBeFalse()
        ->and($driver->verifySignature(logsContext(), $body, []))->toBeFalse();
})->with('logs drivers');

test('unpacking yields a source-tagged event with the expected message', function (callable $make, string $header, callable $payload, bool $native, string $message): void {
    $driver = $make();

    $items = $driver->unpack(logsContext(), $payload())->items;

    expect($items)->not->toBeEmpty()
        ->and($items[0]['source'])->toBe($driver->key())
        ->and($items[0]['message'])->toBe($message)
        ->and(array_key_exists('native_key', $items[0]))->toBe($native);
})->with('logs drivers');

test('the resolver honours the native-key stance', function (callable $make, string $header, callable $payload, bool $native, string $message, ?string $expectedKey): void {
    $driver = $make();
    $event = $driver->unpack(logsContext(), $payload())->items[0];
    $key = app(GroupKeyResolver::class)->resolve($event);

    if ($native) {
        expect($key)->toBe($expectedKey);
    } else {
        expect($key)->toBeString()->not->toBe('')
            ->and($key)->not->toStartWith($driver->key() . ':');
    }
})->with('logs drivers');
