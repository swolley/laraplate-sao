<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Models\Label;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;

uses(RefreshDatabase::class);

test('a label name is unique within a project but repeatable across projects', function (): void {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();

    Label::factory()->for($projectA)->create(['name' => 'bug']);
    Label::factory()->for($projectB)->create(['name' => 'bug']);

    expect(fn (): Label => Label::factory()->for($projectA)->create(['name' => 'bug']))
        ->toThrow(QueryException::class);
});

test('a label attaches to and detaches from a ticket', function (): void {
    $project = Project::factory()->create();
    $ticket = Ticket::factory()->forProject($project)->create();
    $label = Label::factory()->for($project)->create(['name' => 'urgent']);

    $ticket->labels()->attach($label);
    expect($ticket->labels()->get()->pluck('id'))->toContain($label->id);

    $ticket->labels()->detach($label);
    expect($ticket->fresh()->labels()->get()->pluck('id'))->not->toContain($label->id);
});

test('a ticket lists only its own labels', function (): void {
    $project = Project::factory()->create();
    $ticket = Ticket::factory()->forProject($project)->create();
    $other = Ticket::factory()->forProject($project)->create();

    $mine = Label::factory()->for($project)->create(['name' => 'mine']);
    $theirs = Label::factory()->for($project)->create(['name' => 'theirs']);

    $ticket->labels()->attach($mine);
    $other->labels()->attach($theirs);

    expect($ticket->labels()->get()->pluck('id')->all())->toBe([$mine->id]);
});
