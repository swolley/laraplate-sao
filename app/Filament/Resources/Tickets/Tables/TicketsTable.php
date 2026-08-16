<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Tickets\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;
use Modules\SAO\Enums\TicketPriority;

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
                    TextColumn::make('labels.name')
                        ->badge()
                        ->label('Labels')
                        ->toggleable(),
                    TextColumn::make('due_at')
                        ->dateTime()
                        ->sortable()
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('reporter.name')
                        ->searchable(),
                    TextColumn::make('assignee.name')
                        ->searchable(),
                    TextColumn::make('lock_version')
                        ->numeric()
                        ->sortable(),
                );
            },
            filters: static function (Collection $default_filters): void {
                $default_filters->push(
                    SelectFilter::make('priority')
                        ->options(TicketPriority::class),
                    SelectFilter::make('labels')
                        ->relationship('labels', 'name')
                        ->multiple()
                        ->preload(),
                    Filter::make('overdue')
                        ->label('Overdue')
                        ->query(static fn (Builder $query): Builder => $query->overdue()),
                );
            },
        );
    }
}
