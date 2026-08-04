<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\TicketStatuses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;
use Modules\SAO\Enums\StatusCategory;

final class TicketStatusForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        return self::configureForm($schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('category')
                    ->options(StatusCategory::class)
                    ->required(),
                TextInput::make('colour')
                    ->required()
                    ->default('gray'),
                TextInput::make('order_column')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]));
    }
}
