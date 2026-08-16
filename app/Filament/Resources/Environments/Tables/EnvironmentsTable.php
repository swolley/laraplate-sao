<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Environments\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class EnvironmentsTable
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
                    TextColumn::make('name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('current_version')
                        ->label('Version')
                        ->searchable()
                        ->placeholder('—'),
                    TextColumn::make('last_seen_at')
                        ->label('Last seen')
                        ->dateTime()
                        ->sortable()
                        ->placeholder('never'),
                );
            },
        );
    }
}
