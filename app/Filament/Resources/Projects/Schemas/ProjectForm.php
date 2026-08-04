<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Projects\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;

final class ProjectForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        return self::configureForm($schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('key_prefix')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('next_ticket_number')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->required(),
            ]));
    }
}
