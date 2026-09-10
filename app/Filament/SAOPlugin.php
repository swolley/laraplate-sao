<?php

declare(strict_types=1);

namespace Modules\SAO\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

final class SAOPlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'SAO';
    }

    public function getId(): string
    {
        return 'sao';
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Own the module's navigation group instead of having the panel list every module:
     * the group is created here, with this module's icon, when the plugin registers.
     */
    public function afterRegister(Panel $panel): void
    {
        $panel->navigationGroups([
            NavigationGroup::make()
                ->label('SAO')
                ->icon(Heroicon::OutlinedTicket),
        ]);
    }
}
