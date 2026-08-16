<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Connections;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\SAO\Filament\Resources\Connections\Pages\CreateConnection;
use Modules\SAO\Filament\Resources\Connections\Pages\EditConnection;
use Modules\SAO\Filament\Resources\Connections\Pages\ListConnections;
use Modules\SAO\Filament\Resources\Connections\Schemas\ConnectionForm;
use Modules\SAO\Filament\Resources\Connections\Tables\ConnectionsTable;
use Modules\SAO\Models\Connection;
use Override;
use UnitEnum;

final class ConnectionResource extends Resource
{
    protected static ?string $model = Connection::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'SAO';

    #[Override]
    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'sao/connections';
    }

    public static function form(Schema $schema): Schema
    {
        return ConnectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConnectionsTable::configure($table);
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
            'index' => ListConnections::route('/'),
            'create' => CreateConnection::route('/create'),
            'edit' => EditConnection::route('/{record}/edit'),
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
