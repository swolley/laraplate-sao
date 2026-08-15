<?php

declare(strict_types=1);

use Modules\SAO\Drivers\Contracts\IssuesCapability;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Tests\Support\Drivers\FakeIssuesDriver;

test('a driver advertises its key, capabilities and ingest modes', function (): void {
    $driver = new FakeIssuesDriver;

    expect($driver->key())->toBe('fake')
        ->and($driver->capabilities())->toContain(Capability::Issues)
        ->and($driver->ingestModes())->not->toBeEmpty()
        ->and($driver)->toBeInstanceOf(IssuesCapability::class);
});

test('the configuration schema flags secret fields', function (): void {
    $schema = (new FakeIssuesDriver)->configurationSchema();

    $secretNames = array_map(
        static fn ($field): string => $field->name,
        $schema->secretFields(),
    );

    expect($secretNames)->toContain('token')
        ->and($secretNames)->not->toContain('project')
        ->and($schema->field('project')?->secret)->toBeFalse();
});

test('a capability list returns a Page that reports whether more pages remain', function (): void {
    $context = new ConnectionContext(baseUrl: 'https://example.test', credentials: ['token' => 'x']);
    $page = (new FakeIssuesDriver)->list($context);

    expect($page->items)->toHaveCount(2)
        ->and($page->hasMore())->toBeFalse();
});

test('status translation is driven by the passed-in map, not hardcoded', function (): void {
    $driver = new FakeIssuesDriver;

    expect($driver->translateStatus(['Done' => 'resolved'], 'Done'))->toBe('resolved')
        ->and($driver->translateStatus(['Done' => 'resolved'], 'Unknown'))->toBeNull();
});

test('the health check returns a healthy result for the fake driver', function (): void {
    $context = new ConnectionContext(baseUrl: null, credentials: []);

    expect((new FakeIssuesDriver)->healthCheck($context)->healthy)->toBeTrue();
});
