<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Deployments\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class DeploymentsTable
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
                        ->sortable(),
                    TextColumn::make('version')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('status')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('environment.name')
                        ->label('Environment')
                        ->placeholder('—')
                        ->sortable(),
                    TextColumn::make('release.version')
                        ->label('Release')
                        ->placeholder('—'),
                    TextColumn::make('connection.name')
                        ->label('Connection')
                        ->placeholder('—')
                        ->sortable(),
                    TextColumn::make('started_at')
                        ->dateTime()
                        ->sortable(),
                    TextColumn::make('finished_at')
                        ->dateTime()
                        ->placeholder('—')
                        ->sortable(),
                );
            },
        );
    }
}
