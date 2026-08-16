<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\ClosureAudits\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\SAO\Models\ClosureAudit;

final class ClosureAuditInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('ticket.key')
                    ->label('Ticket'),
                TextEntry::make('policy.name')
                    ->label('Policy')
                    ->placeholder('—'),
                TextEntry::make('action')
                    ->badge(),
                TextEntry::make('reporting_environment')
                    ->label('Environment')
                    ->placeholder('—'),
                IconEntry::make('is_premature')
                    ->label('Premature closure')
                    ->boolean(),
                TextEntry::make('closed_at')
                    ->label('Closed at')
                    ->dateTime(),
                TextEntry::make('reopened_at')
                    ->label('Reopened at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('returned_after_seconds')
                    ->label('Returned after (seconds)')
                    ->numeric()
                    ->placeholder('—'),
                TextEntry::make('conditions_held')
                    ->label('Closed because')
                    ->badge()
                    ->columnSpanFull()
                    ->state(static fn (ClosureAudit $record): array => self::heldConditions($record)),
            ]);
    }

    /**
     * The conditions that held, as `key ✓` / `key ✗` labels — the auditable
     * "closed because".
     *
     * @return list<string>
     */
    private static function heldConditions(ClosureAudit $record): array
    {
        $labels = [];

        foreach ($record->conditions_held as $key => $outcome) {
            $held = (bool) ($outcome['held'] ?? false);
            $labels[] = $key . ' ' . ($held ? '✓' : '✗');
        }

        return $labels;
    }
}
