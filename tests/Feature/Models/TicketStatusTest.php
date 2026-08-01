<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Models\TicketStatus;

uses(RefreshDatabase::class);

test('a status carries a canonical category as an enum', function (): void {
    $status = TicketStatus::factory()->create([
        'name' => 'In review',
        'category' => StatusCategory::InProgress,
    ]);

    expect($status->category)->toBe(StatusCategory::InProgress);
});

/**
 * Core models validate on save, so the unique: rule rejects the duplicate before
 * the database constraint is reached.
 */
test('statuses are global, so two of them may not share a name', function (): void {
    TicketStatus::factory()->create(['name' => 'In review']);

    expect(fn (): TicketStatus => TicketStatus::factory()->create(['name' => 'In review']))
        ->toThrow(Modules\Core\Overrides\ContextualValidationException::class);
});

test('a terminal category answers the question phase 6 will ask', function (): void {
    $closed = TicketStatus::factory()->category(StatusCategory::Closed)->create();
    $resolved = TicketStatus::factory()->category(StatusCategory::Resolved)->create();

    expect($closed->category->isTerminal())->toBeTrue();
    expect($resolved->category->isTerminal())->toBeFalse();
});

test('the create rules constrain the category to the canonical set', function (): void {
    $rules = (new TicketStatus)->getRules()['create'];

    expect($rules['category'])->toContain(StatusCategory::validationRule());
});

test('a new status reports its defaults before being refreshed', function (): void {
    $status = new TicketStatus;

    expect($status->colour)->toBe('gray');
});

test('statuses are ordered on creation and readable in order', function (): void {
    $first = TicketStatus::factory()->create(['name' => 'Open']);
    $second = TicketStatus::factory()->create(['name' => 'Doing']);
    $third = TicketStatus::factory()->create(['name' => 'Done']);

    expect($first->order_column)->toBeLessThan($second->order_column);
    expect($second->order_column)->toBeLessThan($third->order_column);

    $names = TicketStatus::query()->ordered()->pluck('name')->all();

    expect($names)->toBe(['Open', 'Doing', 'Done']);
});
