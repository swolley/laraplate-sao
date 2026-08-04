<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\TicketStatuses;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\SAO\Filament\Resources\TicketStatuses\Pages\CreateTicketStatus;
use Modules\SAO\Filament\Resources\TicketStatuses\Pages\EditTicketStatus;
use Modules\SAO\Filament\Resources\TicketStatuses\Pages\ListTicketStatuses;
use Modules\SAO\Filament\Resources\TicketStatuses\Schemas\TicketStatusForm;
use Modules\SAO\Filament\Resources\TicketStatuses\Tables\TicketStatusesTable;
use Modules\SAO\Models\TicketStatus;
use Override;
use UnitEnum;

final class TicketStatusResource extends Resource
{
    protected static ?string $model = TicketStatus::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'SAO';

    #[Override]
    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'sao/ticket-statuses';
    }

    public static function form(Schema $schema): Schema
    {
        return TicketStatusForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketStatusesTable::configure($table);
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
            'index' => ListTicketStatuses::route('/'),
            'create' => CreateTicketStatus::route('/create'),
            'edit' => EditTicketStatus::route('/{record}/edit'),
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
