<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\ApplicationContent\Data\ApplicationContentHit;
use Modules\SAO\ApplicationContent\SaoTicketEvidenceProjector;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('projects a ticket to a safe hit', function (): void {
    $ticket = Ticket::factory()->create(['title' => 'Deploy failed', 'description' => str_repeat('x', 5000)]);

    $hit = (new SaoTicketEvidenceProjector)->project($ticket->fresh(['status', 'type', 'project', 'assignee']), 'en', 'lexical', 0.9);

    expect($hit)->toBeInstanceOf(ApplicationContentHit::class)
        ->and($hit->source)->toBe('sao.tickets')
        ->and($hit->module)->toBe('sao')
        ->and($hit->entity)->toBe('tickets')
        ->and($hit->label)->toBe('Deploy failed')
        ->and($hit->recordKey)->toBe($ticket->key)
        ->and($hit->canonicalReference)->toBe('/app/sao/tickets/' . $ticket->key)
        ->and($hit->id)->toBe('sao.tickets:' . $ticket->key)
        ->and(mb_strlen($hit->excerpt))->toBeLessThanOrEqual(1000);

    // safe projection: no comment/attachment/internal payloads leak
    $json = json_encode(get_object_vars($hit));
    expect($json)->not->toContain('assignee_id')->not->toContain('ticket_status_id');
});

it('returns null when the ticket has no title', function (): void {
    $ticket = Ticket::factory()->make(['title' => '']);
    expect((new SaoTicketEvidenceProjector)->project($ticket, 'en', 'lexical', null))->toBeNull();
});

it('returns null when the title reduces to nothing after sanitization', function (): void {
    $ticket = Ticket::factory()->make(['title' => '<b></b>']);
    expect((new SaoTicketEvidenceProjector)->project($ticket, 'en', 'lexical', null))->toBeNull();
});

it('falls back to the title when the description is empty', function (): void {
    $ticket = Ticket::factory()->create(['title' => 'Login broken', 'description' => null]);

    $hit = (new SaoTicketEvidenceProjector)->project($ticket->fresh(), 'en', 'lexical', 0.5);

    expect($hit)->toBeInstanceOf(ApplicationContentHit::class)
        ->and($hit->excerpt)->toBe('Login broken')
        ->and($hit->truncated)->toBeFalse();
});

it('sanitizes markup and control characters so the hit stays plain text', function (): void {
    $ticket = Ticket::factory()->create([
        'title' => "Crash <b>now</b>\x07",
        'description' => "<p>Stack&nbsp;trace</p>\x00 line",
    ]);

    $hit = (new SaoTicketEvidenceProjector)->project($ticket->fresh(), 'en', 'lexical', 0.5);

    expect($hit->label)->toBe('Crash now')
        ->and($hit->excerpt)->toBe('Stack trace line');
});
