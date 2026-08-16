<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\ClosureAudits;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\SAO\Filament\Resources\ClosureAudits\Pages\ListClosureAudits;
use Modules\SAO\Filament\Resources\ClosureAudits\Pages\ViewClosureAudit;
use Modules\SAO\Filament\Resources\ClosureAudits\Schemas\ClosureAuditInfolist;
use Modules\SAO\Filament\Resources\ClosureAudits\Tables\ClosureAuditsTable;
use Modules\SAO\Models\ClosureAudit;
use Override;
use UnitEnum;

/**
 * A read-only surface: closure audits are written by the closure engine, never
 * by hand. The resource offers a list and a view only — no create, no edit —
 * so the "closed because / returned after" record stays an honest trail.
 */
final class ClosureAuditResource extends Resource
{
    protected static ?string $model = ClosureAudit::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'SAO';

    #[Override]
    protected static ?int $navigationSort = 63;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'sao/closure-audits';
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClosureAuditInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClosureAuditsTable::configure($table);
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
            'index' => ListClosureAudits::route('/'),
            'view' => ViewClosureAudit::route('/{record}'),
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
