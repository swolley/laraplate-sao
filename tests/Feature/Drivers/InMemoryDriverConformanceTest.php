<?php

declare(strict_types=1);

use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Tests\Support\Conformance\IssuesConformance;
use Modules\SAO\Tests\Support\Conformance\ReleasesConformance;
use Modules\SAO\Tests\Support\Drivers\InMemoryDriver;

test('the in-memory reference driver passes the issues conformance battery', function (): void {
    $context = new ConnectionContext(baseUrl: null, credentials: ['token' => 'x']);

    IssuesConformance::assert(new InMemoryDriver, $context);
});

test('the in-memory reference driver passes the releases conformance battery', function (): void {
    $context = new ConnectionContext(baseUrl: null, credentials: ['token' => 'x']);

    ReleasesConformance::assert(new InMemoryDriver, $context);
});

test('the reference driver is resolvable through the registry by capability', function (): void {
    $registry = new DriverRegistry;
    $registry->register(new InMemoryDriver);

    expect($registry->get('in-memory')->key())->toBe('in-memory')
        ->and($registry->withCapability(Capability::Issues))->toHaveCount(1)
        ->and($registry->withCapability(Capability::Releases))->toHaveCount(1);
});
