<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\SAO\Data\OwnershipEvidence;
use Modules\SAO\Enums\OwnershipRule;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\OwnershipSuggestionService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new OwnershipSuggestionService();
});

test('no evidence yields no suggestion', function (): void {
    $ticket = Ticket::factory()->create();

    expect($this->service->suggest($ticket, []))->toBeNull();
});

test('a stronger rule wins over a higher score', function (): void {
    $ticket = Ticket::factory()->create();
    $codeowner = User::factory()->create();
    $frequentToucher = User::factory()->create();

    $suggestion = $this->service->suggest($ticket, [
        new OwnershipEvidence($frequentToucher->id, OwnershipRule::RecentTouch, 99.0, ['app/A.php']),
        new OwnershipEvidence($codeowner->id, OwnershipRule::Codeowners, 1.0, ['app/B.php']),
    ]);

    expect($suggestion)->not->toBeNull()
        ->and($suggestion->suggested_user_id)->toBe($codeowner->id)
        ->and($suggestion->rule)->toBe(OwnershipRule::Codeowners)
        ->and($suggestion->evidence['paths'])->toBe(['app/B.php']);
});

test('within the same rule the higher score wins, then the lower user id', function (): void {
    $ticket = Ticket::factory()->create();
    $low = User::factory()->create();
    $high = User::factory()->create();

    $byScore = $this->service->suggest($ticket, [
        new OwnershipEvidence($low->id, OwnershipRule::BlameConcentration, 2.0),
        new OwnershipEvidence($high->id, OwnershipRule::BlameConcentration, 5.0),
    ]);

    expect($byScore->suggested_user_id)->toBe($high->id);

    $tie = $this->service->suggest($ticket, [
        new OwnershipEvidence($high->id, OwnershipRule::BlameConcentration, 5.0),
        new OwnershipEvidence($low->id, OwnershipRule::BlameConcentration, 5.0),
    ]);

    expect($tie->suggested_user_id)->toBe($low->id);
});

test('a suggestion never changes the ticket assignee', function (): void {
    $assignee = User::factory()->create();
    $ticket = Ticket::factory()->create(['assignee_id' => $assignee->id]);
    $suggested = User::factory()->create();

    $this->service->suggest($ticket, [
        new OwnershipEvidence($suggested->id, OwnershipRule::PathOwner, 1.0),
    ]);

    expect($ticket->fresh()->assignee_id)->toBe($assignee->id);
});
