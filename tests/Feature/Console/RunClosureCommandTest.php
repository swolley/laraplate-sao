<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the command runs cleanly with nothing to close', function (): void {
    $this->artisan('sao:closure:run')
        ->expectsOutputToContain('0 closed, 0 proposed')
        ->assertSuccessful();
});

test('the command auto-closes eligible tickets when the setting is on', function (): void {
    config(['sao.closure.auto_close.enabled' => true]);
    ['ticket' => $ticket, 'done' => $done] = coord_closure_fixture();

    $this->artisan('sao:closure:run', ['--env' => 'production'])->assertSuccessful();

    expect($ticket->refresh()->ticket_status_id)->toBe($done->id);
});
