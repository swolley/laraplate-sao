<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\TicketTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;

final class TicketTypeForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        return self::configureForm($schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('icon'),
                TextInput::make('colour')
                    ->required()
                    ->default('gray'),
                TextInput::make('workflow_scheme_id')
                    ->required()
                    ->numeric(),
                Toggle::make('is_defect')
                    ->required(),
            ]));
    }
}
