<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\IngestEvents;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\SAO\Filament\Resources\IngestEvents\Pages\ListIngestEvents;
use Modules\SAO\Filament\Resources\IngestEvents\Pages\ViewIngestEvent;
use Modules\SAO\Filament\Resources\IngestEvents\Schemas\IngestEventInfolist;
use Modules\SAO\Filament\Resources\IngestEvents\Tables\IngestEventsTable;
use Modules\SAO\Models\IngestEvent;
use Override;
use UnitEnum;

/**
 * A read-only surface: ingest events are written by the ingest pipeline (webhook
 * transport and generic ingest), never by hand. The resource offers a list and a
 * view only — no create, no edit — so the auditable "every delivery, with an
 * explicit outcome" record stays machine-written.
 */
final class IngestEventResource extends Resource
{
    protected static ?string $model = IngestEvent::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'SAO';

    #[Override]
    protected static ?int $navigationSort = 65;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $recordTitleAttribute = 'delivery_id';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'sao/ingest-events';
    }

    public static function infolist(Schema $schema): Schema
    {
        return IngestEventInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IngestEventsTable::configure($table);
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
            'index' => ListIngestEvents::route('/'),
            'view' => ViewIngestEvent::route('/{record}'),
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
