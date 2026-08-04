<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\TicketTypes\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class TicketTypesTable
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
                    TextColumn::make('slug')
                        ->searchable(),
                    TextColumn::make('icon')
                        ->searchable(),
                    TextColumn::make('colour')
                        ->searchable(),
                    TextColumn::make('workflow_scheme_id')
                        ->numeric()
                        ->sortable(),
                    IconColumn::make('is_defect')
                        ->boolean(),
                );
            },
        );
    }
}
