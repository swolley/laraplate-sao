<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\ClosurePolicies;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\SAO\Filament\Resources\ClosurePolicies\Pages\CreateClosurePolicy;
use Modules\SAO\Filament\Resources\ClosurePolicies\Pages\EditClosurePolicy;
use Modules\SAO\Filament\Resources\ClosurePolicies\Pages\ListClosurePolicies;
use Modules\SAO\Filament\Resources\ClosurePolicies\Schemas\ClosurePolicyForm;
use Modules\SAO\Filament\Resources\ClosurePolicies\Tables\ClosurePoliciesTable;
use Modules\SAO\Models\ClosurePolicy;
use Override;
use UnitEnum;

final class ClosurePolicyResource extends Resource
{
    protected static ?string $model = ClosurePolicy::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'SAO';

    #[Override]
    protected static ?int $navigationSort = 62;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'sao/closure-policies';
    }

    public static function form(Schema $schema): Schema
    {
        return ClosurePolicyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClosurePoliciesTable::configure($table);
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
            'index' => ListClosurePolicies::route('/'),
            'create' => CreateClosurePolicy::route('/create'),
            'edit' => EditClosurePolicy::route('/{record}/edit'),
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
