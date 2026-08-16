<?php

declare(strict_types=1);

use Modules\SAO\Drivers\External\SentryDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Modules\SAO\Ingest\GroupKeyResolver;

function sentryContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(baseUrl: 'https://sentry.io', credentials: ['secret' => 'shh']),
        remoteIdentifier: 'acme/backend',
    );
}

function sentryPayload(): string
{
    return json_encode([
        'action' => 'created',
        'data' => [
            'issue' => [
                'id' => '42',
                'title' => 'TypeError: undefined is not a function',
                'culprit' => 'app/handler.js in run',
                'level' => 'error',
                'web_url' => 'https://sentry.io/organizations/acme/issues/42/',
            ],
            'event' => [
                'environment' => 'production',
                'datetime' => '2026-08-16T10:00:00Z',
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}

test('the sentry driver declares the logs capability with push ingest', function (): void {
    $driver = new SentryDriver;

    expect($driver->key())->toBe('sentry')
        ->and($driver->capabilities())->toBe([Capability::Logs])
        ->and($driver->ingestModes())->toBe([IngestMode::Push])
        ->and($driver->carriesNativeGroupKey())->toBeTrue();
});

test('a delivery signed with the shared secret verifies, a tampered one does not', function (): void {
    $driver = new SentryDriver;
    $payload = sentryPayload();
    $signature = hash_hmac('sha256', $payload, 'shh');

    expect($driver->verifySignature(sentryContext(), $payload, ['Sentry-Hook-Signature' => $signature]))->toBeTrue()
        ->and($driver->verifySignature(sentryContext(), $payload, ['Sentry-Hook-Signature' => 'wrong']))->toBeFalse()
        ->and($driver->verifySignature(sentryContext(), $payload, []))->toBeFalse();
});

test('unpacking a webhook yields a native-keyed event', function (): void {
    $page = (new SentryDriver)->unpack(sentryContext(), sentryPayload());

    expect($page->items)->toHaveCount(1);

    $event = $page->items[0];

    expect($event['native_key'])->toBe('42')
        ->and($event['source'])->toBe('sentry')
        ->and($event['message'])->toBe('TypeError: undefined is not a function')
        ->and($event['environment'])->toBe('production')
        ->and($event['level'])->toBe('error');
});

test('the resolver namespaces the sentry native key so it cannot collide', function (): void {
    $event = (new SentryDriver)->unpack(sentryContext(), sentryPayload())->items[0];
    $resolver = app(GroupKeyResolver::class);

    expect($resolver->resolve($event))->toBe('sentry:42');
});

test('a payload without an issue id yields no events', function (): void {
    $page = (new SentryDriver)->unpack(sentryContext(), json_encode(['data' => []], JSON_THROW_ON_ERROR));

    expect($page->items)->toBe([]);
});
