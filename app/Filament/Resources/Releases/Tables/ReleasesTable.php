<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Releases\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class ReleasesTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(
                    TextColumn::make('project.name')
                        ->label('Project')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('version')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('status')
                        ->badge()
                        ->searchable(),
                    TextColumn::make('tags_count')
                        ->label('Tags')
                        ->counts('tags')
                        ->numeric(),
                    TextColumn::make('released_at')
                        ->dateTime()
                        ->sortable()
                        ->placeholder('—'),
                );
            },
        );
    }
}
