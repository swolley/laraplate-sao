<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Filament\Resources\Tickets\RelationManagers\RelationsRelationManager;
use Modules\SAO\Filament\Resources\Tickets\TicketResource;

uses(RefreshDatabase::class);

function sao_read(string $relativePath): string
{
    return (string) file_get_contents(dirname(__DIR__, 3) . '/app/Filament/Resources/Tickets/' . $relativePath);
}

test('the ticket form exposes the enrichment fields', function (): void {
    $form = sao_read('Schemas/TicketForm.php');

    expect($form)->toContain("DateTimePicker::make('due_at')")
        ->and($form)->toContain("Select::make('labels')")
        ->and($form)->toContain("Select::make('watchers')")
        ->and($form)->toContain('SpatieMediaLibraryFileUpload::make');
});

test('the ticket table shows due date and labels with filters', function (): void {
    $table = sao_read('Tables/TicketsTable.php');

    expect($table)->toContain("TextColumn::make('due_at')")
        ->and($table)->toContain("TextColumn::make('labels.name')")
        ->and($table)->toContain('overdue');
});

test('the resource registers the relations manager', function (): void {
    expect(TicketResource::getRelations())->toContain(RelationsRelationManager::class)
        ->and(class_exists(RelationsRelationManager::class))->toBeTrue();
});

test('the relations manager edits the outgoing relations', function (): void {
    $property = new ReflectionProperty(RelationsRelationManager::class, 'relationship');

    expect($property->getValue())->toBe('relations');
});
