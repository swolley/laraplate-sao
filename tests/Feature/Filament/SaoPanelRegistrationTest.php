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

it('registers its own navigation groups with the module icon on the first one', function (): void {
    $groups = collect(Filament::getPanel('admin')->getNavigationGroups())
        ->filter(static fn (NavigationGroup $group): bool => str_starts_with($group->getLabel(), 'SAO - '));

    expect($groups->map(static fn (NavigationGroup $group): string => $group->getLabel())->values()->all())
        ->toBe(['SAO - Ticketing', 'SAO - Delivery', 'SAO - Signals', 'SAO - Governance'])
        ->and($groups->first()->getIcon())->toBe(Heroicon::OutlinedTicket);
});

it('registers every navigation group used by the SAO resources and pages', function (): void {
    $registered = collect(Filament::getPanel('admin')->getNavigationGroups())
        ->map(static fn (NavigationGroup $group): string => $group->getLabel())
        ->all();

    $used = collect(Filament::getPanel('admin')->getResources())
        ->merge(Filament::getPanel('admin')->getPages())
        ->filter(static fn (string $class): bool => str_starts_with($class, 'Modules\\SAO\\'))
        ->map(static fn (string $class): ?string => $class::getNavigationGroup())
        ->unique()
        ->values()
        ->all();

    expect($used)->not->toBeEmpty()
        ->and(array_diff($used, $registered))->toBe([]);
});
