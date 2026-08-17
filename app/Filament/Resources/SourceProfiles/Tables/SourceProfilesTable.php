<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\SourceProfiles\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;
use Modules\SAO\Models\SourceProfile;

final class SourceProfilesTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(
                    TextColumn::make('name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('matchers')
                        ->label('Matchers')
                        ->badge()
                        ->state(static fn (SourceProfile $record): int => count($record->matchers)),
                    TextColumn::make('field_bindings')
                        ->label('Bindings')
                        ->badge()
                        ->state(static fn (SourceProfile $record): int => count($record->field_bindings)),
                    IconColumn::make('is_active')
                        ->label('Active')
                        ->boolean(),
                );
            },
        );
    }
}
