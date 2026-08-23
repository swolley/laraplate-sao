<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Import\Enums\ImportSourceFormat;
use Modules\Core\Import\Support\ImportRunner;
use Modules\Core\Models\ImportSession;
use Modules\SAO\Models\Ticket;

uses(RefreshDatabase::class);

/**
 * @param  array<string, string>  $mapping
 */
function ticketImportSession(string $csv, array $mapping): ImportSession
{
    Storage::fake('local');
    Storage::disk('local')->put('tickets.csv', $csv);

    return ImportSession::factory()->create([
        'entity_key' => 'sao.ticket',
        'source_format' => ImportSourceFormat::Csv,
        'file_disk' => 'local',
        'file_path' => 'tickets.csv',
        'original_filename' => 'tickets.csv',
        'mapping' => $mapping,
    ]);
}

test('the ticket importer opens tickets per project and reports unknown projects', function (): void {
    sync_fixture(); // project SAO with a default ticket type

    $session = ticketImportSession(
        "project_key,title,description,external_id\n"
        . "SAO,First bug,Broke,EXT-1\n"
        . "SAO,Second bug,,EXT-2\n"
        . "BAD,Orphan,,EXT-3\n",
        ['project_key' => 'project_key', 'title' => 'title', 'description' => 'description', 'external_id' => 'external_id'],
    );

    app(ImportRunner::class)->process($session);
    $session->refresh();

    expect($session->created_rows)->toBe(2)
        ->and($session->failed_rows)->toBe(1)
        ->and(Ticket::query()->count())->toBe(2)
        ->and($session->rowErrors()->first()->errors)->toHaveKey('project_key');
});

test('re-importing the same external id updates the ticket instead of duplicating', function (): void {
    sync_fixture();

    $mapping = ['project_key' => 'project_key', 'title' => 'title', 'external_id' => 'external_id'];

    app(ImportRunner::class)->process(
        ticketImportSession("project_key,title,external_id\nSAO,Original title,EXT-1\n", $mapping),
    );

    expect(Ticket::query()->count())->toBe(1)
        ->and(Ticket::query()->value('title'))->toBe('Original title');

    $second = ticketImportSession("project_key,title,external_id\nSAO,Revised title,EXT-1\n", $mapping);
    app(ImportRunner::class)->process($second);

    expect($second->fresh()->updated_rows)->toBe(1)
        ->and(Ticket::query()->count())->toBe(1)
        ->and(Ticket::query()->value('title'))->toBe('Revised title');
});
