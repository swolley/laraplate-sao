<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\ClosureAction;
use Modules\SAO\Models\ClosurePolicy;
use Modules\SAO\Services\ClosureCoordinator;

uses(RefreshDatabase::class);

test('with auto-close off a satisfied close policy only proposes', function (): void {
    config(['sao.closure.auto_close.enabled' => false]);
    ['ticket' => $ticket, 'done' => $done] = coord_closure_fixture();

    $audits = app(ClosureCoordinator::class)->forTicket($ticket->refresh(), 'production');

    expect($audits)->toHaveCount(1)
        ->and($audits[0]->action)->toBe(ClosureAction::Propose)
        ->and($ticket->refresh()->ticket_status_id)->not->toBe($done->id);
});

test('with auto-close on a satisfied close policy closes the ticket', function (): void {
    config(['sao.closure.auto_close.enabled' => true]);
    ['ticket' => $ticket, 'done' => $done] = coord_closure_fixture();

    $audits = app(ClosureCoordinator::class)->forTicket($ticket->refresh(), 'production');

    expect($audits)->toHaveCount(1)
        ->and($audits[0]->action)->toBe(ClosureAction::Close)
        ->and($ticket->refresh()->ticket_status_id)->toBe($done->id);
});

test('the downgrade does not persist the policy action', function (): void {
    config(['sao.closure.auto_close.enabled' => false]);
    ['ticket' => $ticket] = coord_closure_fixture();

    app(ClosureCoordinator::class)->forTicket($ticket->refresh(), 'production');

    expect(ClosurePolicy::query()->where('project_id', $ticket->project_id)->sole()->action)
        ->toBe(ClosureAction::Close);
});
