<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Tickets\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;
use Modules\SAO\Enums\TicketPriority;

final class TicketForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        return self::configureForm($schema
            ->components([
                Select::make('project_id')
                    ->relationship('project', 'name')
                    ->required(),
                TextInput::make('number')
                    ->required()
                    ->numeric(),
                TextInput::make('key')
                    ->required(),
                TextInput::make('ticket_type_id')
                    ->required()
                    ->numeric(),
                TextInput::make('ticket_status_id')
                    ->required()
                    ->numeric(),
                Select::make('priority')
                    ->options(TicketPriority::class)
                    ->default('normal')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('reporter_id')
                    ->relationship('reporter', 'name'),
                Select::make('assignee_id')
                    ->relationship('assignee', 'name'),
            ]));
    }
}
