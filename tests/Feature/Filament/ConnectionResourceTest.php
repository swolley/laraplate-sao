<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Filament\Resources\Connections\ConnectionResource;
use Modules\SAO\Models\Connection;

uses(RefreshDatabase::class);

function sao_connection_source(string $relativePath): string
{
    return (string) file_get_contents(dirname(__DIR__, 3) . '/app/Filament/Resources/Connections/' . $relativePath);
}

test('the connection resource is bound to the connection in the SAO group', function (): void {
    expect(ConnectionResource::getModel())->toBe(Connection::class)
        ->and(ConnectionResource::getNavigationGroup())->toBe('SAO')
        ->and(ConnectionResource::getSlug())->toBe('sao/connections')
        ->and(ConnectionResource::getRecordTitleAttribute())->toBe('name');
});

test('the connection form exposes driver, capabilities and credential fields', function (): void {
    $form = sao_connection_source('Schemas/ConnectionForm.php');

    expect($form)->toContain("Select::make('driver_key')")
        ->and($form)->toContain("Select::make('capabilities')")
        ->and($form)->toContain("make('credential')")
        ->and($form)->toContain("TextInput::make('credential_ref')");
});

test('the stored credential is never rendered back into the form (write-only)', function (): void {
    $form = sao_connection_source('Schemas/ConnectionForm.php');

    // Blanked on hydrate and only written when the operator types a new value.
    expect($form)->toContain('afterStateHydrated')
        ->and($form)->toContain('dehydrated');
});

test('the connection table shows the driver, capabilities and health', function (): void {
    $table = sao_connection_source('Tables/ConnectionsTable.php');

    expect($table)->toContain("TextColumn::make('driver_key')")
        ->and($table)->toContain("TextColumn::make('capabilities')")
        ->and($table)->toContain("TextColumn::make('health_state')");
});
