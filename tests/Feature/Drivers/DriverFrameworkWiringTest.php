<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Tests\Support\Drivers\InMemoryDriver;

test('the driver registry is a shared singleton', function (): void {
    expect(app(DriverRegistry::class))->toBe(app(DriverRegistry::class));
});

test('drivers listed in config are registered when the registry resolves', function (): void {
    Config::set('sao.drivers.registered', [InMemoryDriver::class]);

    expect(app(DriverRegistry::class)->has('in-memory'))->toBeTrue();
});

test('the redmine driver is registered by default', function (): void {
    expect(app(DriverRegistry::class)->has('redmine'))->toBeTrue();
});

test('the registry honours an emptied config', function (): void {
    Config::set('sao.drivers.registered', []);

    expect(app(DriverRegistry::class)->all())->toBe([]);
});
