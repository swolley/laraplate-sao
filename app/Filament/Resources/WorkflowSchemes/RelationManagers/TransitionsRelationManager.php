<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\WorkflowSchemes\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Core\Models\Permission;

/**
 * The transitions of a workflow scheme.
 *
 * They are edited here rather than as a resource of their own because a
 * transition has no meaning outside its scheme: it is the scheme's content, not
 * a thing someone browses.
 */
final class TransitionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transitions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('from_status_id')
                    ->label('From status')
                    ->relationship('fromStatus', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('(new ticket)')
                    ->helperText('Leave empty to declare the status a new ticket starts in. A scheme may have only one such transition.'),
                Select::make('to_status_id')
                    ->label('To status')
                    ->relationship('toStatus', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('label')
                    ->helperText('The wording of the button a person will click.')
                    ->required()
                    ->maxLength(255),
                // Picked, never typed. The value goes straight to `Gate::allows()`, which
                // fails closed on a name that does not exist, so a typo used to deny the
                // transition for everybody, permanently, without a word anywhere.
                Select::make('required_permission')
                    ->label('Required permission')
                    ->helperText('Optional. Only holders of this permission may take the transition.')
                    ->searchable()
                    ->getSearchResultsUsing(static fn (string $search): array => Permission::query()
                        ->where('name', 'like', '%' . $search . '%')
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'name')
                        ->all())
                    // A name saved before this field became a Select, or one whose
                    // permission has since been dropped, still has to show itself: it is
                    // the transition nobody can take, and hiding it would hide the cause.
                    ->getOptionLabelUsing(static fn (string $value): string => self::permissionLabel($value)),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('fromStatus.name')
                    ->label('From')
                    // An empty cell would read as missing data rather than as the
                    // deliberate marker of the creation transition.
                    ->placeholder('(new ticket)')
                    ->badge(),
                TextColumn::make('toStatus.name')
                    ->label('To')
                    ->badge(),
                TextColumn::make('label')
                    ->searchable(),
                TextColumn::make('required_permission')
                    ->formatStateUsing(static fn (?string $state): ?string => $state === null ? null : self::permissionLabel($state))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * The name as stored, marked when no permission answers to it any more.
     */
    private static function permissionLabel(string $name): string
    {
        return Permission::query()->where('name', $name)->exists()
            ? $name
            : sprintf('%s (missing)', $name);
    }
}
