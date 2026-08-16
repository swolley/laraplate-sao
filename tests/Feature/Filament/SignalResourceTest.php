<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Filament\Resources\Signals\RelationManagers\OccurrencesRelationManager;
use Modules\SAO\Filament\Resources\Signals\SignalResource;
use Modules\SAO\Models\Signal;

uses(RefreshDatabase::class);

function sao_signal_source(string $relativePath): string
{
    return (string) file_get_contents(dirname(__DIR__, 3) . '/app/Filament/Resources/Signals/' . $relativePath);
}

test('the signal resource is bound to the signal in the SAO group', function (): void {
    expect(SignalResource::getModel())->toBe(Signal::class)
        ->and(SignalResource::getNavigationGroup())->toBe('SAO')
        ->and(SignalResource::getSlug())->toBe('sao/signals')
        ->and(SignalResource::getRecordTitleAttribute())->toBe('group_key');
});

test('signals are not manually created', function (): void {
    expect(array_keys(SignalResource::getPages()))->not->toContain('create');
});

test('the signal form makes state editable and the machine fields read-only', function (): void {
    $form = sao_signal_source('Schemas/SignalForm.php');

    expect($form)->toContain("Select::make('state')")
        ->and($form)->toContain("TextInput::make('group_key')")
        ->and($form)->toContain('disabled');
});

test('the signal table shows state and counters with filters', function (): void {
    $table = sao_signal_source('Tables/SignalsTable.php');

    expect($table)->toContain("TextColumn::make('state')")
        ->and($table)->toContain("TextColumn::make('occurrence_count')")
        ->and($table)->toContain("SelectFilter::make('state')");
});

test('the resource registers the occurrences relation manager', function (): void {
    expect(SignalResource::getRelations())->toContain(OccurrencesRelationManager::class)
        ->and(class_exists(OccurrencesRelationManager::class))->toBeTrue();
});
