<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\ClosureAudits\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class ClosureAuditsTable
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
                    TextColumn::make('action')
                        ->badge(),
                    TextColumn::make('reporting_environment')
                        ->label('Environment')
                        ->placeholder('—'),
                    IconColumn::make('is_premature')
                        ->label('Premature')
                        ->boolean(),
                    TextColumn::make('closed_at')
                        ->dateTime()
                        ->sortable(),
                    TextColumn::make('reopened_at')
                        ->dateTime()
                        ->placeholder('—')
                        ->sortable(),
                );
            },
        );
    }
}
