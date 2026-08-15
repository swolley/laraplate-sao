<?php

declare(strict_types=1);

use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Exceptions\DuplicateDriverException;
use Modules\SAO\Exceptions\UnknownDriverException;
use Modules\SAO\Tests\Support\Drivers\FakeIssuesDriver;
use Modules\SAO\Tests\Support\Drivers\FakeReleasesDriver;

test('a registered driver resolves by key', function (): void {
    $registry = new DriverRegistry;
    $driver = new FakeIssuesDriver;
    $registry->register($driver);

    expect($registry->has('fake'))->toBeTrue()
        ->and($registry->has('nope'))->toBeFalse()
        ->and($registry->get('fake'))->toBe($driver);
});

test('the registry filters drivers by capability', function (): void {
    $registry = new DriverRegistry;
    $registry->register(new FakeIssuesDriver);
    $registry->register(new FakeReleasesDriver);

    expect($registry->all())->toHaveCount(2)
        ->and(array_map(static fn ($d): string => $d->key(), $registry->withCapability(Capability::Issues)))->toBe(['fake'])
        ->and(array_map(static fn ($d): string => $d->key(), $registry->withCapability(Capability::Releases)))->toBe(['fake-releases'])
        ->and($registry->withCapability(Capability::Logs))->toBe([]);
});

test('resolving an unknown driver throws', function (): void {
    $registry = new DriverRegistry;

    expect(fn (): mixed => $registry->get('nope'))->toThrow(UnknownDriverException::class);
});

test('registering the same key twice throws', function (): void {
    $registry = new DriverRegistry;
    $registry->register(new FakeIssuesDriver);

    expect(fn () => $registry->register(new FakeIssuesDriver))->toThrow(DuplicateDriverException::class);
});
