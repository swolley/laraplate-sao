<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;

uses(RefreshDatabase::class);

test('overdue() returns past-due tickets and excludes future or undated ones', function (): void {
    $overdue = Ticket::factory()->create(['due_at' => now()->subDay()]);
    $future = Ticket::factory()->create(['due_at' => now()->addWeek()]);
    $undated = Ticket::factory()->create(['due_at' => null]);

    $ids = Ticket::query()->overdue()->pluck('id');

    expect($ids)->toContain($overdue->id)
        ->not->toContain($future->id)
        ->not->toContain($undated->id);
});

test('overdue() excludes tickets already in a terminal status', function (): void {
    $closed = TicketStatus::factory()->category(StatusCategory::Closed)->create();
    $ticket = Ticket::factory()->create([
        'due_at' => now()->subDay(),
        'ticket_status_id' => $closed->id,
    ]);

    expect(Ticket::query()->overdue()->pluck('id'))->not->toContain($ticket->id);
});

test('dueWithin() returns tickets due within the given number of days', function (): void {
    $soon = Ticket::factory()->create(['due_at' => now()->addDays(3)]);
    $later = Ticket::factory()->create(['due_at' => now()->addDays(30)]);

    $ids = Ticket::query()->dueWithin(7)->pluck('id');

    expect($ids)->toContain($soon->id)
        ->not->toContain($later->id);
});
