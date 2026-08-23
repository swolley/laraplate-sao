<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Enums\SyncDirection;
use Modules\SAO\Services\BindingCutoverService;
use Modules\SAO\Tests\Support\Drivers\RecordingIssuesDriver;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(DriverRegistry::class)->register(new RecordingIssuesDriver);
});

test('cutover makes SAO authoritative by disabling the binding sync by default', function (): void {
    [$project, $type] = sync_fixture();
    $binding = sync_binding(SyncDirection::Inbound, $project, $type);

    app(BindingCutoverService::class)->cutover($binding);

    expect($binding->refresh()->sync_direction)->toBe(SyncDirection::Disabled);
});

test('cutover can keep pushing outbound during a transition', function (): void {
    [$project, $type] = sync_fixture();
    $binding = sync_binding(SyncDirection::Inbound, $project, $type);

    app(BindingCutoverService::class)->cutover($binding, SyncDirection::Outbound);

    expect($binding->refresh()->sync_direction)->toBe(SyncDirection::Outbound);
});
