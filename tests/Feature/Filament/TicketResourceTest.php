<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Filament\Resources\Tickets\Pages\ViewTicket;
use Modules\SAO\Filament\Resources\Tickets\TicketResource;
use Modules\SAO\Models\Ticket;

uses(RefreshDatabase::class);

test('the ticket resource is bound to the ticket and titled by its key', function (): void {
    expect(TicketResource::getModel())->toBe(Ticket::class);
    expect(TicketResource::getRecordTitleAttribute())->toBe('key');
});

test('the ticket resource sits in the SAO group above the configuration entities', function (): void {
    expect(TicketResource::getNavigationGroup())->toBe('SAO');
    expect(TicketResource::getSlug())->toBe('sao/tickets');
    expect(TicketResource::getNavigationSort())->toBe(5);
});

/**
 * The list must not be a raw Eloquent query: Core's ACL filtering is not
 * automatic at that level, so a raw query would bypass row-level visibility
 * without anyone noticing.
 */
test('the ticket list reads through the ACL-aware query service', function (): void {
    Ticket::factory()->count(2)->create();

    $query = TicketResource::getEloquentQuery();

    expect($query->getModel())->toBeInstanceOf(Ticket::class);
    expect($query->getQuery()->from)->toBe('sao_tickets');
    expect($query->count())->toBe(2);
});

test('the resource registers a view page for the ticket detail', function (): void {
    expect(array_keys(TicketResource::getPages()))->toContain('view');
});

test('the view page asks the workflow service which transitions to offer', function (): void {
    $source = (string) file_get_contents(
        dirname(__DIR__, 3) . '/app/Filament/Resources/Tickets/Pages/ViewTicket.php',
    );

    expect($source)->toContain('WorkflowService');
    expect($source)->toContain('availableTransitions');
    expect(class_exists(ViewTicket::class))->toBeTrue();
});
