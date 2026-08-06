<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Tickets\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\SAO\Models\Ticket;

final class TicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('project.name')
                    ->label('Project'),
                TextEntry::make('number')
                    ->numeric(),
                TextEntry::make('key'),
                TextEntry::make('ticket_type_id')
                    ->numeric(),
                TextEntry::make('ticket_status_id')
                    ->numeric(),
                TextEntry::make('priority')
                    ->badge(),
                TextEntry::make('title'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('reporter.name')
                    ->label('Reporter')
                    ->placeholder('-'),
                TextEntry::make('assignee.name')
                    ->label('Assignee')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Ticket $record): bool => $record->trashed()),
            ]);
    }
}
