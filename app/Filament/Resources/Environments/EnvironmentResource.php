<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Environments;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\SAO\Filament\Resources\Environments\Pages\CreateEnvironment;
use Modules\SAO\Filament\Resources\Environments\Pages\EditEnvironment;
use Modules\SAO\Filament\Resources\Environments\Pages\ListEnvironments;
use Modules\SAO\Filament\Resources\Environments\Schemas\EnvironmentForm;
use Modules\SAO\Filament\Resources\Environments\Tables\EnvironmentsTable;
use Modules\SAO\Models\Environment;
use Override;
use UnitEnum;

final class EnvironmentResource extends Resource
{
    protected static ?string $model = Environment::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'SAO';

    #[Override]
    protected static ?int $navigationSort = 61;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'sao/environments';
    }

    public static function form(Schema $schema): Schema
    {
        return EnvironmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EnvironmentsTable::configure($table);
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
            'index' => ListEnvironments::route('/'),
            'create' => CreateEnvironment::route('/create'),
            'edit' => EditEnvironment::route('/{record}/edit'),
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
