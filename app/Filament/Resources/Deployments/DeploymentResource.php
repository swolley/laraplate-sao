<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Deployments;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\SAO\Filament\Resources\Deployments\Pages\ListDeployments;
use Modules\SAO\Filament\Resources\Deployments\Pages\ViewDeployment;
use Modules\SAO\Filament\Resources\Deployments\Schemas\DeploymentInfolist;
use Modules\SAO\Filament\Resources\Deployments\Tables\DeploymentsTable;
use Modules\SAO\Models\Deployment;
use Override;
use UnitEnum;

/**
 * A read-only surface: deployments are written by the deploy ingest (webhook
 * transport or the `sao:deploy:record` command), never by hand. The resource
 * offers a list and a view only — no create, no edit — so the durable deploy
 * history stays machine-written and the environment version census remains a
 * projection of it.
 */
final class DeploymentResource extends Resource
{
    protected static ?string $model = Deployment::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'SAO';

    #[Override]
    protected static ?int $navigationSort = 66;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?string $recordTitleAttribute = 'version';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'sao/deployments';
    }

    public static function infolist(Schema $schema): Schema
    {
        return DeploymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeploymentsTable::configure($table);
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
            'index' => ListDeployments::route('/'),
            'view' => ViewDeployment::route('/{record}'),
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
