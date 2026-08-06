<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Tickets\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class TicketsTable
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
                    TextColumn::make('number')
                        ->numeric()
                        ->sortable(),
                    TextColumn::make('key')
                        ->searchable(),
                    TextColumn::make('ticket_type_id')
                        ->numeric()
                        ->sortable(),
                    TextColumn::make('ticket_status_id')
                        ->numeric()
                        ->sortable(),
                    TextColumn::make('priority')
                        ->badge()
                        ->searchable(),
                    TextColumn::make('title')
                        ->searchable(),
                    TextColumn::make('reporter.name')
                        ->searchable(),
                    TextColumn::make('assignee.name')
                        ->searchable(),
                    TextColumn::make('lock_version')
                        ->numeric()
                        ->sortable(),
                );
            },
        );
    }
}
