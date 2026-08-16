<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\OwnershipSuggestions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class OwnershipSuggestionsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(
                    TextColumn::make('ticket.key')
                        ->label('Ticket')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('suggestedUser.name')
                        ->label('Suggested owner')
                        ->placeholder('—')
                        ->searchable(),
                    TextColumn::make('rule')
                        ->badge(),
                    TextColumn::make('score')
                        ->numeric()
                        ->sortable(),
                );
            },
        );
    }
}
