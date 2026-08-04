<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\WorkflowSchemes;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\SAO\Filament\Resources\WorkflowSchemes\Pages\CreateWorkflowScheme;
use Modules\SAO\Filament\Resources\WorkflowSchemes\Pages\EditWorkflowScheme;
use Modules\SAO\Filament\Resources\WorkflowSchemes\Pages\ListWorkflowSchemes;
use Modules\SAO\Filament\Resources\WorkflowSchemes\RelationManagers\TransitionsRelationManager;
use Modules\SAO\Filament\Resources\WorkflowSchemes\Schemas\WorkflowSchemeForm;
use Modules\SAO\Filament\Resources\WorkflowSchemes\Tables\WorkflowSchemesTable;
use Modules\SAO\Models\WorkflowScheme;
use Override;
use UnitEnum;

final class WorkflowSchemeResource extends Resource
{
    protected static ?string $model = WorkflowScheme::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'SAO';

    #[Override]
    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'sao/workflow-schemes';
    }

    public static function form(Schema $schema): Schema
    {
        return WorkflowSchemeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkflowSchemesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TransitionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkflowSchemes::route('/'),
            'create' => CreateWorkflowScheme::route('/create'),
            'edit' => EditWorkflowScheme::route('/{record}/edit'),
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
