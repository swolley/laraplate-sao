<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Tickets;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\SAO\Filament\Resources\Tickets\Pages\CreateTicket;
use Modules\SAO\Filament\Resources\Tickets\Pages\EditTicket;
use Modules\SAO\Filament\Resources\Tickets\Pages\ListTickets;
use Modules\SAO\Filament\Resources\Tickets\Pages\ViewTicket;
use Modules\SAO\Filament\Resources\Tickets\Schemas\TicketForm;
use Modules\SAO\Filament\Resources\Tickets\Schemas\TicketInfolist;
use Modules\SAO\Filament\Resources\Tickets\Tables\TicketsTable;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\TicketQueryService;
use Override;
use UnitEnum;

final class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'SAO';

    #[Override]
    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'key';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'sao/tickets';
    }

    /**
     * Reads go through the ACL-aware service, never raw Eloquent: Core's ACL
     * filtering is not automatic at the Eloquent level, so a raw query would
     * bypass row-level visibility silently.
     *
     * @return Builder<Ticket>
     */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return app(TicketQueryService::class)->visible();
    }

    public static function form(Schema $schema): Schema
    {
        return TicketForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TicketInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'create' => CreateTicket::route('/create'),
            'view' => ViewTicket::route('/{record}'),
            'edit' => EditTicket::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
