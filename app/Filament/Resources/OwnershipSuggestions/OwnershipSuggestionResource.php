<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\OwnershipSuggestions;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\SAO\Filament\Resources\OwnershipSuggestions\Pages\ListOwnershipSuggestions;
use Modules\SAO\Filament\Resources\OwnershipSuggestions\Pages\ViewOwnershipSuggestion;
use Modules\SAO\Filament\Resources\OwnershipSuggestions\Schemas\OwnershipSuggestionInfolist;
use Modules\SAO\Filament\Resources\OwnershipSuggestions\Tables\OwnershipSuggestionsTable;
use Modules\SAO\Models\OwnershipSuggestion;
use Override;
use UnitEnum;

/**
 * A read-only surface: ownership suggestions are proposals produced from code
 * evidence and are never applied automatically (D14). The resource offers a
 * list and a view only, so a human can read the proposal and its evidence and
 * decide — assignment stays a human act.
 */
final class OwnershipSuggestionResource extends Resource
{
    protected static ?string $model = OwnershipSuggestion::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'SAO';

    #[Override]
    protected static ?int $navigationSort = 64;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'sao/ownership-suggestions';
    }

    public static function infolist(Schema $schema): Schema
    {
        return OwnershipSuggestionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OwnershipSuggestionsTable::configure($table);
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
            'index' => ListOwnershipSuggestions::route('/'),
            'view' => ViewOwnershipSuggestion::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
