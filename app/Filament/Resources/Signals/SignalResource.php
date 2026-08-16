<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Signals;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\SAO\Filament\Resources\Signals\Pages\EditSignal;
use Modules\SAO\Filament\Resources\Signals\Pages\ListSignals;
use Modules\SAO\Filament\Resources\Signals\RelationManagers\OccurrencesRelationManager;
use Modules\SAO\Filament\Resources\Signals\Schemas\SignalForm;
use Modules\SAO\Filament\Resources\Signals\Tables\SignalsTable;
use Modules\SAO\Models\Signal;
use Override;
use UnitEnum;

final class SignalResource extends Resource
{
    protected static ?string $model = Signal::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'SAO';

    #[Override]
    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $recordTitleAttribute = 'group_key';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'sao/signals';
    }

    public static function form(Schema $schema): Schema
    {
        return SignalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SignalsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            OccurrencesRelationManager::class,
        ];
    }

    /**
     * No create page: signals are opened by the ingest pipeline, never by hand.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListSignals::route('/'),
            'edit' => EditSignal::route('/{record}/edit'),
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
