<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Projects\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\SyncDirection;

/**
 * A project's integration bindings: which connection serves which capability,
 * in which sync direction, with the per-installation status/priority maps.
 *
 * Edited here rather than as a resource of its own because a binding has no
 * meaning outside its project — it is the project's integration wiring.
 */
final class BindingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bindings';

    protected static ?string $title = 'Integrations';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('connection_id')
                    ->label('Connection')
                    ->relationship('remoteConnection', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('capability')
                    ->options(Capability::class)
                    ->required()
                    ->helperText('Must be a capability the chosen connection exposes.'),
                TextInput::make('remote_identifier')
                    ->label('Remote identifier')
                    ->helperText('The remote project id/key/slug this binding targets.')
                    ->maxLength(255),
                Select::make('sync_direction')
                    ->options(SyncDirection::class)
                    ->default(SyncDirection::Disabled->value)
                    ->required(),
                KeyValue::make('status_map')
                    ->label('Status map')
                    ->keyLabel('Remote status')
                    ->valueLabel('Canonical category')
                    ->helperText('Remote status name → canonical category.'),
                KeyValue::make('priority_map')
                    ->label('Priority map')
                    ->keyLabel('Remote priority')
                    ->valueLabel('Canonical priority'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('remote_identifier')
            ->columns([
                TextColumn::make('remoteConnection.name')
                    ->label('Connection')
                    ->badge(),
                TextColumn::make('capability')
                    ->badge(),
                TextColumn::make('sync_direction')
                    ->label('Direction')
                    ->badge(),
                TextColumn::make('remote_identifier')
                    ->label('Remote')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
