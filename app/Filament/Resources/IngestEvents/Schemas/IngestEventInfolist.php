<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\IngestEvents\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\SAO\Models\IngestEvent;

final class IngestEventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('delivery_id')
                    ->label('Delivery'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('outcome')
                    ->placeholder('—'),
                TextEntry::make('connection.name')
                    ->label('Connection')
                    ->placeholder('—'),
                TextEntry::make('sourceProfile.name')
                    ->label('Source profile')
                    ->placeholder('—'),
                TextEntry::make('project.name')
                    ->label('Project')
                    ->placeholder('—'),
                TextEntry::make('winning_rule')
                    ->label('Winning rule')
                    ->placeholder('—'),
                TextEntry::make('signal.group_key')
                    ->label('Signal')
                    ->placeholder('—'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('payload')
                    ->label('Payload')
                    ->columnSpanFull()
                    ->state(static fn (IngestEvent $record): string => (string) json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            ]);
    }
}
