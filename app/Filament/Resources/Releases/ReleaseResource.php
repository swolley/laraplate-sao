<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Releases;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\SAO\Filament\Resources\Releases\Pages\CreateRelease;
use Modules\SAO\Filament\Resources\Releases\Pages\EditRelease;
use Modules\SAO\Filament\Resources\Releases\Pages\ListReleases;
use Modules\SAO\Filament\Resources\Releases\RelationManagers\TagsRelationManager;
use Modules\SAO\Filament\Resources\Releases\Schemas\ReleaseForm;
use Modules\SAO\Filament\Resources\Releases\Tables\ReleasesTable;
use Modules\SAO\Models\Release;
use Override;
use UnitEnum;

final class ReleaseResource extends Resource
{
    protected static ?string $model = Release::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'SAO';

    #[Override]
    protected static ?int $navigationSort = 60;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'version';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'sao/releases';
    }

    public static function form(Schema $schema): Schema
    {
        return ReleaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReleasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TagsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReleases::route('/'),
            'create' => CreateRelease::route('/create'),
            'edit' => EditRelease::route('/{record}/edit'),
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
