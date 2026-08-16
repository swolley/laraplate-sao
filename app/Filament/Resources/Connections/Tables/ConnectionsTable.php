<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Connections\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class ConnectionsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(
                    TextColumn::make('name')
                        ->searchable(),
                    TextColumn::make('driver_key')
                        ->label('Driver')
                        ->badge()
                        ->searchable(),
                    TextColumn::make('capabilities')
                        ->badge(),
                    TextColumn::make('health_state')
                        ->label('Health')
                        ->badge(),
                    TextColumn::make('base_url')
                        ->label('Base URL')
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('last_checked_at')
                        ->dateTime()
                        ->placeholder('never')
                        ->toggleable(),
                );
            },
        );
    }
}
