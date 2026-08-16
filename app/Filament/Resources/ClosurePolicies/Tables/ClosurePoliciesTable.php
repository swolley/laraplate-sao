<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\ClosurePolicies\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class ClosurePoliciesTable
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
                    TextColumn::make('action')
                        ->badge(),
                    TextColumn::make('conditions')
                        ->label('Conditions')
                        ->badge()
                        ->state(static fn ($record): array => array_map(
                            static fn (array $condition): string => (string) ($condition['key'] ?? ''),
                            $record->conditions,
                        )),
                    IconColumn::make('is_active')
                        ->label('Active')
                        ->boolean(),
                );
            },
        );
    }
}
