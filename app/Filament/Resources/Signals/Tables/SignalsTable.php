<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Signals\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;
use Modules\SAO\Enums\SignalState;

final class SignalsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(
                    TextColumn::make('project.name')
                        ->searchable(),
                    TextColumn::make('group_key')
                        ->label('Group key')
                        ->badge()
                        ->searchable(),
                    TextColumn::make('state')
                        ->badge(),
                    TextColumn::make('occurrence_count')
                        ->label('Occurrences')
                        ->numeric()
                        ->sortable(),
                    TextColumn::make('algo_version')
                        ->label('Algo')
                        ->numeric()
                        ->toggleable(),
                    TextColumn::make('first_seen_at')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(),
                    TextColumn::make('last_seen_at')
                        ->dateTime()
                        ->sortable(),
                );
            },
            filters: static function (Collection $default_filters): void {
                $default_filters->push(
                    SelectFilter::make('state')
                        ->options(SignalState::class),
                    SelectFilter::make('project')
                        ->relationship('project', 'name')
                        ->searchable()
                        ->preload(),
                );
            },
        );
    }
}
