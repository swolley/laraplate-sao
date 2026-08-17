<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\IngestEvents\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class IngestEventsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(
                    TextColumn::make('delivery_id')
                        ->label('Delivery')
                        ->limit(24)
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('status')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('outcome')
                        ->placeholder('—')
                        ->searchable(),
                    TextColumn::make('connection.name')
                        ->label('Connection')
                        ->placeholder('—')
                        ->sortable(),
                    TextColumn::make('project.name')
                        ->label('Project')
                        ->placeholder('—')
                        ->sortable(),
                    TextColumn::make('signal.group_key')
                        ->label('Signal')
                        ->placeholder('—')
                        ->limit(32),
                    TextColumn::make('created_at')
                        ->dateTime()
                        ->sortable(),
                );
            },
        );
    }
}
