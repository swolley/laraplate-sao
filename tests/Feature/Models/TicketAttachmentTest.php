<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\SAO\Models\Ticket;

uses(RefreshDatabase::class);

test('a ticket stores a file in its attachments collection', function (): void {
    Storage::fake(config('media-library.disk_name'));

    $ticket = Ticket::factory()->create();

    $ticket->addMedia(UploadedFile::fake()->create('report.pdf', 12))
        ->toMediaCollection('attachments');

    $attachments = $ticket->getMedia('attachments');

    expect($attachments)->toHaveCount(1)
        ->and($attachments->first()->file_name)->toBe('report.pdf')
        ->and($attachments->first())->toBeInstanceOf(Modules\Core\Models\Media::class);
});
