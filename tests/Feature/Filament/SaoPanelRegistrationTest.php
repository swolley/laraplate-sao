<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Icons\Heroicon;
use Modules\SAO\Filament\Pages\TicketBoard;
use Modules\SAO\Filament\Resources\Projects\ProjectResource;
use Modules\SAO\Filament\Resources\Tickets\TicketResource;

it('registers the SAO Filament surfaces on the admin panel', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->getPlugin('sao'))->not->toBeNull()
        ->and($panel->getResources())->toContain(TicketResource::class, ProjectResource::class)
        ->and($panel->getPages())->toContain(TicketBoard::class);
});

it('registers its own navigation group with the module icon', function (): void {
    $group = collect(Filament::getPanel('admin')->getNavigationGroups())
        ->first(static fn (NavigationGroup $group): bool => $group->getLabel() === 'SAO');

    expect($group)->not->toBeNull()
        ->and($group->getIcon())->toBe(Heroicon::OutlinedTicket);
});
