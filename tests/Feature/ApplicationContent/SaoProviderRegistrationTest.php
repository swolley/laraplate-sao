<?php

declare(strict_types=1);

use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderRegistryInterface;

it('registers the sao.tickets application-content provider', function (): void {
    $registry = app(ApplicationContentRetrievalProviderRegistryInterface::class);
    $descriptor = $registry->descriptorFor('sao.tickets');

    expect($descriptor)->not->toBeNull()
        ->and($descriptor->module)->toBe('sao')
        ->and($descriptor->entity)->toBe('tickets');
});
