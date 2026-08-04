<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\TicketStatuses\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class TicketStatusesTable
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
                    TextColumn::make('category')
                        ->badge()
                        ->searchable(),
                    TextColumn::make('colour')
                        ->searchable(),
                    TextColumn::make('order_column')
                        ->numeric()
                        ->sortable(),
                );
            },
        );
    }
}
