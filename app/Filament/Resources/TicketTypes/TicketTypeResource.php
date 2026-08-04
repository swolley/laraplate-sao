<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\TicketTypes;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\SAO\Filament\Resources\TicketTypes\Pages\CreateTicketType;
use Modules\SAO\Filament\Resources\TicketTypes\Pages\EditTicketType;
use Modules\SAO\Filament\Resources\TicketTypes\Pages\ListTicketTypes;
use Modules\SAO\Filament\Resources\TicketTypes\Schemas\TicketTypeForm;
use Modules\SAO\Filament\Resources\TicketTypes\Tables\TicketTypesTable;
use Modules\SAO\Models\TicketType;
use Override;
use UnitEnum;

final class TicketTypeResource extends Resource
{
    protected static ?string $model = TicketType::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'SAO';

    #[Override]
    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'sao/ticket-types';
    }

    public static function form(Schema $schema): Schema
    {
        return TicketTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketTypesTable::configure($table);
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
            'index' => ListTicketTypes::route('/'),
            'create' => CreateTicketType::route('/create'),
            'edit' => EditTicketType::route('/{record}/edit'),
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
