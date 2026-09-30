<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Filament\Resources\Tickets\Pages\ListTickets;
use Modules\SAO\Filament\Resources\Tickets\Pages\ViewTicket;
use Modules\SAO\Filament\Resources\Tickets\TicketResource;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketComment;
use Modules\SAO\Models\TicketStatus;

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

/**
 * The backoffice shows a ticket for support and maintenance: its type and status by name and its
 * history read-only. Working the ticket, commenting included, belongs to the SAO application.
 */
function ticketPanelSuperadmin(): User
{
    if (! class_exists(App\Models\User::class)) {
        class_alias(User::class, App\Models\User::class);
    }

    $superadmin = App\Models\User::query()->create(User::factory()->raw());
    $superadmin->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    return $superadmin;
}

test('the ticket list filters by status category', function (): void {
    $open = Ticket::factory()->create(['ticket_status_id' => TicketStatus::factory()->category(StatusCategory::Open)]);
    $closed = Ticket::factory()->create(['ticket_status_id' => TicketStatus::factory()->category(StatusCategory::Closed)]);
    $this->actingAs(ticketPanelSuperadmin());
    Filament::setCurrentPanel('admin');

    Livewire::test(ListTickets::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$open, $closed])
        ->filterTable('status_category', StatusCategory::Closed->value)
        ->assertCanSeeTableRecords([$closed])
        ->assertCanNotSeeTableRecords([$open]);
});

test('the view page shows the ticket by name and its timeline read-only', function (): void {
    $superadmin = ticketPanelSuperadmin();
    $ticket = Ticket::factory()->create();
    TicketComment::postFor($ticket, 'Looking into it.', ChangeContext::forUser($superadmin));

    $this->actingAs($superadmin);
    Filament::setCurrentPanel('admin');

    Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
        ->assertSee($ticket->type->name)
        ->assertSee($ticket->status->name)
        ->assertSee('Looking into it.')
        ->assertDontSee('postComment');

    expect(method_exists(ViewTicket::class, 'postComment'))->toBeFalse();
});
