<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\SAO\Models\Label;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('builds a denormalized searchable document with related names', function (): void {
    $assignee = User::factory()->create(['name' => 'Ada Lovelace']);
    $watcher = User::factory()->create(['name' => 'Grace Hopper']);

    $ticket = Ticket::factory()->create([
        'title' => 'Deploy failed on staging',
        'description' => 'pipeline red',
        'assignee_id' => $assignee->id,
    ]);

    $label = Label::factory()->create(['project_id' => $ticket->project_id, 'name' => 'urgent']);
    $ticket->labels()->attach($label);
    $ticket->watchers()->attach($watcher);

    $doc = $ticket->fresh(['project', 'type', 'status', 'assignee', 'reporter', 'watchers', 'labels'])->toSearchableArray();

    expect($doc)->toHaveKeys([
        'title', 'description', 'priority', 'key', 'number', 'due_at',
        'project', 'type', 'status', 'assignee', 'reporter', 'watchers', 'labels',
    ])
        ->and($doc['title'])->toBe('Deploy failed on staging')
        ->and($doc['description'])->toBe('pipeline red')
        ->and($doc['key'])->toBe($ticket->key)
        ->and($doc['project'])->toHaveKey('name')
        ->and($doc['project'])->not->toHaveKey('key')
        ->and($doc['status'])->toHaveKeys(['id', 'name', 'category'])
        ->and($doc['status']['category'])->toBeString()
        ->and($doc['assignee'])->toBe(['id' => $assignee->id, 'name' => 'Ada Lovelace'])
        ->and($doc['reporter'])->toBeNull()
        ->and($doc['watchers'])->toBe([['id' => $watcher->id, 'name' => 'Grace Hopper']])
        ->and($doc['labels'])->toBeArray()
        ->and($doc['labels'])->toBe([['id' => $label->id, 'name' => 'urgent']]);
});

it('eager-loads the relations it denormalizes', function (): void {
    expect((new Ticket)->toSearchableWith())
        ->toContain('project')->toContain('type')->toContain('status')
        ->toContain('assignee')->toContain('reporter')->toContain('watchers')->toContain('labels');
});
