<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Filament\Pages\TicketBoard;

uses(RefreshDatabase::class);

test('the board page sits in the SAO navigation group', function (): void {
    expect(TicketBoard::getNavigationGroup())->toBe('SAO')
        ->and(TicketBoard::getSlug())->toBe('sao/board');
});

test('the board page resolves through the board service and moves through the workflow service', function (): void {
    $source = (string) file_get_contents(
        dirname(__DIR__, 3) . '/app/Filament/Pages/TicketBoard.php',
    );

    expect($source)->toContain('TicketBoardService')
        ->and($source)->toContain('WorkflowService')
        ->and($source)->toContain('availableTransitions')
        ->and(class_exists(TicketBoard::class))->toBeTrue();
});
