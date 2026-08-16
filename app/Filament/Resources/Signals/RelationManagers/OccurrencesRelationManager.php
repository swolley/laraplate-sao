<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Signals\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The occurrences of a signal, read-only: occurrences are recorded by the ingest
 * pipeline, never edited by hand. Shown newest first for triage.
 */
final class OccurrencesRelationManager extends RelationManager
{
    protected static string $relationship = 'occurrences';

    protected static ?string $title = 'Occurrences';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('environment')
                    ->badge()
                    ->placeholder('—'),
            ]);
    }
}
