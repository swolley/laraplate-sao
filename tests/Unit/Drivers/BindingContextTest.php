<?php

declare(strict_types=1);

use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;

test('a binding context composes the connection context and adds binding config', function (): void {
    $connection = new ConnectionContext(baseUrl: 'https://x.test', credentials: ['token' => 't']);

    $context = new BindingContext(
        connection: $connection,
        remoteIdentifier: 'proj-1',
        config: ['project' => 5],
        statusMap: ['Done' => 'resolved'],
        priorityMap: ['H' => 'high'],
    );

    expect($context->connection)->toBe($connection)
        ->and($context->baseUrl())->toBe('https://x.test')
        ->and($context->credentials())->toBe(['token' => 't'])
        ->and($context->remoteIdentifier)->toBe('proj-1')
        ->and($context->config)->toBe(['project' => 5])
        ->and($context->statusMap)->toBe(['Done' => 'resolved'])
        ->and($context->priorityMap)->toBe(['H' => 'high']);
});

test('a binding context has empty binding defaults', function (): void {
    $context = new BindingContext(new ConnectionContext(baseUrl: null, credentials: []));

    expect($context->remoteIdentifier)->toBeNull()
        ->and($context->config)->toBe([])
        ->and($context->statusMap)->toBe([])
        ->and($context->priorityMap)->toBe([]);
});
