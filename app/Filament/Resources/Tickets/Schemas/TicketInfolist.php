<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Tickets\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;
use Modules\Core\Models\User;
use Modules\SAO\Data\TimelineEntry;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\TicketTimelineService;

final class TicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('project.name')
                    ->label('Project'),
                TextEntry::make('number')
                    ->numeric(),
                TextEntry::make('key'),
                TextEntry::make('type.name')
                    ->label('Type'),
                TextEntry::make('status.name')
                    ->label('Status')
                    ->badge(),
                TextEntry::make('priority')
                    ->badge(),
                TextEntry::make('title'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('reporter.name')
                    ->label('Reporter')
                    ->placeholder('-'),
                TextEntry::make('assignee.name')
                    ->label('Assignee')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Ticket $record): bool => $record->trashed()),
                ViewEntry::make('timeline')
                    ->label('Timeline')
                    ->view('sao::filament.tickets.timeline')
                    ->state(static fn (Ticket $record): array => self::timeline($record))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The ticket's history, read-only: the backoffice shows what happened for support and
     * maintenance, while working the ticket (commenting) belongs to the SAO application.
     *
     * @return list<array{occurred_at: string, kind: string, who: string, body: ?string, fields: list<string>}>
     */
    private static function timeline(Ticket $ticket): array
    {
        $entries = resolve(TicketTimelineService::class)->for($ticket);
        $authors = User::query()
            ->whereKey($entries->map(static fn (TimelineEntry $entry): ?int => $entry->authorId())->filter()->unique()->all())
            ->pluck('name', 'id');

        return $entries->map(static fn (TimelineEntry $entry): array => [
            'occurred_at' => $entry->occurredAt()->toDateTimeString(),
            'kind' => $entry->kind(),
            'who' => $entry->authorId() !== null
                ? (string) ($authors[$entry->authorId()] ?? '#' . $entry->authorId())
                : ($entry->sourceKey() ?? 'system'),
            'body' => $entry->body(),
            'fields' => array_keys($entry->changes()),
        ])->values()->all();
    }
}
