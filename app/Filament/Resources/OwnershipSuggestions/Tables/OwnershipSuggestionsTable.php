<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\OwnershipSuggestions\Tables;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;
use Modules\SAO\Models\OwnershipSuggestion;
use Modules\SAO\Services\OwnershipSuggestionApplier;

final class OwnershipSuggestionsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(
                    TextColumn::make('ticket.key')
                        ->label('Ticket')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('suggestedUser.name')
                        ->label('Suggested owner')
                        ->placeholder('—')
                        ->searchable(),
                    TextColumn::make('rule')
                        ->badge(),
                    TextColumn::make('score')
                        ->numeric()
                        ->sortable(),
                );
            },
            actions: static function (Collection $default_actions): void {
                // The one sanctioned manual accept: assign the suggested owner to
                // the ticket. Hidden when there is no user to assign (D14 — a
                // suggestion is never applied automatically).
                $default_actions->unshift(
                    Action::make('acceptSuggestion')
                        ->label('Accept')
                        ->icon(Heroicon::OutlinedUserPlus)
                        ->requiresConfirmation()
                        ->visible(static fn (OwnershipSuggestion $record): bool => $record->suggested_user_id !== null)
                        ->action(static function (OwnershipSuggestion $record): void {
                            $ticket = app(OwnershipSuggestionApplier::class)->apply($record);

                            Notification::make()
                                ->title('Owner assigned')
                                ->body("{$record->suggestedUser?->name} is now the assignee of {$ticket->key}.")
                                ->success()
                                ->send();
                        }),
                );
            },
        );
    }
}
