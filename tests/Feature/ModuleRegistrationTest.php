<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

test('the SAO module is discovered and enabled', function (): void {
    $module = Module::find('SAO');

    expect($module)->not->toBeNull('Module::find("SAO") returned null — check modules_statuses.json');
    expect($module->isEnabled())->toBeTrue();
});

test('the SAO config file resolves under the sao namespace', function (): void {
    expect(config('sao.name'))->toBe('SAO');
});
