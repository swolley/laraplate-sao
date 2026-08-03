<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\TicketPriority;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;

uses(RefreshDatabase::class);

test('a ticket carries the key its project allocated', function (): void {
    $project = Project::factory()->create(['key_prefix' => 'SAO']);

    $ticket = Ticket::factory()->forProject($project)->create();

    expect($ticket->key)->toBe('SAO-1');
    expect($ticket->number)->toBe(1);
    expect($ticket->project->id)->toBe($project->id);
});

test('two tickets in one project get distinct keys', function (): void {
    $project = Project::factory()->create(['key_prefix' => 'SAO']);

    $first = Ticket::factory()->forProject($project)->create();
    $second = Ticket::factory()->forProject($project)->create();

    expect($first->key)->toBe('SAO-1');
    expect($second->key)->toBe('SAO-2');
});

test('a new ticket defaults to normal priority', function (): void {
    expect((new Ticket)->priority)->toBe(TicketPriority::Normal);
});

/**
 * WorkflowService is the only path to a status change, so mass assignment must
 * not offer a shortcut around the workflow rules.
 */
test('the status cannot be mass assigned', function (): void {
    $ticket = Ticket::factory()->create();
    $other = TicketStatus::factory()->create();

    expect(fn (): Ticket => $ticket->fill(['ticket_status_id' => $other->id]))
        ->toThrow(Illuminate\Database\Eloquent\MassAssignmentException::class);
});

test('the ticket key is unique across the whole installation', function (): void {
    $project = Project::factory()->create(['key_prefix' => 'SAO']);
    Ticket::factory()->forProject($project)->create();

    expect(fn (): Ticket => Ticket::factory()->create([
        'project_id' => $project->id,
        'number' => 1,
        'key' => 'SAO-1',
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

test('a ticket resolves its type and status', function (): void {
    $ticket = Ticket::factory()->create();

    expect($ticket->type)->not->toBeNull();
    expect($ticket->status)->not->toBeNull();
});

test('optimistic locking rejects a stale write', function (): void {
    $ticket = Ticket::factory()->create(['title' => 'Original']);

    $stale = Ticket::query()->findOrFail($ticket->id);
    $fresh = Ticket::query()->findOrFail($ticket->id);

    $fresh->title = 'Won the race';
    $fresh->save();

    $stale->title = 'Lost the race';

    expect(fn (): bool => $stale->save())
        ->toThrow(Modules\Core\Locking\Exceptions\StaleModelLockingException::class);

    expect($ticket->fresh()->title)->toBe('Won the race');
});
