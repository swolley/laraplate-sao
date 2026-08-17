<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\SourceProfiles;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\SAO\Filament\Resources\SourceProfiles\Pages\CreateSourceProfile;
use Modules\SAO\Filament\Resources\SourceProfiles\Pages\EditSourceProfile;
use Modules\SAO\Filament\Resources\SourceProfiles\Pages\ListSourceProfiles;
use Modules\SAO\Filament\Resources\SourceProfiles\Schemas\SourceProfileForm;
use Modules\SAO\Filament\Resources\SourceProfiles\Tables\SourceProfilesTable;
use Modules\SAO\Models\SourceProfile;
use Override;
use UnitEnum;

/**
 * The authoring surface for generic ingest profiles: an operator defines the
 * matchers that decide which payloads a profile applies to and the field
 * bindings that normalize a payload into canonical fields — supporting a new
 * source becomes a form entry, not a code change. The `sao:ingest:replay`
 * command dry-runs a stored event against a profile to tune it before use.
 */
final class SourceProfileResource extends Resource
{
    protected static ?string $model = SourceProfile::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'SAO';

    #[Override]
    protected static ?int $navigationSort = 66;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'sao/source-profiles';
    }

    public static function form(Schema $schema): Schema
    {
        return SourceProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SourceProfilesTable::configure($table);
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
            'index' => ListSourceProfiles::route('/'),
            'create' => CreateSourceProfile::route('/create'),
            'edit' => EditSourceProfile::route('/{record}/edit'),
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
