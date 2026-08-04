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
                TextInput::make('required_permission')
                    ->helperText('Optional. Only holders of this permission may take the transition.')
                    ->maxLength(255),
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
}
