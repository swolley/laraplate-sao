<?php

declare(strict_types=1);

use Modules\SAO\Filament\Resources\IngestEvents\IngestEventResource;
use Modules\SAO\Models\IngestEvent;

function sao_ingest_event_source(string $relativePath): string
{
    return (string) file_get_contents(dirname(__DIR__, 3) . '/app/Filament/Resources/IngestEvents/' . $relativePath);
}

test('the ingest event resource is bound to its model under the SAO group', function (): void {
    expect(IngestEventResource::getModel())->toBe(IngestEvent::class)
        ->and(IngestEventResource::getNavigationGroup())->toBe('SAO')
        ->and(IngestEventResource::getSlug())->toStartWith('sao/');
});

test('the ingest event resource is read-only: list and view, no create or edit', function (): void {
    expect(array_keys(IngestEventResource::getPages()))->toBe(['index', 'view'])
        ->and(IngestEventResource::canCreate())->toBeFalse();
});

test('the ingest event list offers no create action', function (): void {
    $contents = sao_ingest_event_source('Pages/ListIngestEvents.php');

    expect($contents)->toContain('getHeaderActions')
        ->and($contents)->toContain('return [];');
});

test('the table surfaces the delivery, status, outcome and correlation columns', function (): void {
    $table = sao_ingest_event_source('Tables/IngestEventsTable.php');

    expect($table)->toContain("TextColumn::make('delivery_id')")
        ->and($table)->toContain("TextColumn::make('status')")
        ->and($table)->toContain("TextColumn::make('outcome')")
        ->and($table)->toContain("TextColumn::make('signal.group_key')");
});

test('the infolist renders the raw payload alongside the outcome trail', function (): void {
    $infolist = sao_ingest_event_source('Schemas/IngestEventInfolist.php');

    expect($infolist)->toContain("TextEntry::make('payload')")
        ->and($infolist)->toContain('JSON_PRETTY_PRINT')
        ->and($infolist)->toContain("TextEntry::make('winning_rule')");
});
