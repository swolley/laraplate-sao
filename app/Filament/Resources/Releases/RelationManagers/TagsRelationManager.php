<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Releases\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\SAO\Enums\ReleaseTagKind;

/**
 * The concrete VCS tags realizing a release. Edited here rather than as a
 * resource of its own because a tag has no meaning outside its release.
 */
final class TagsRelationManager extends RelationManager
{
    protected static string $relationship = 'tags';

    protected static ?string $title = 'Tags';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tag')
                    ->helperText('The concrete VCS tag, e.g. v1.4.0 or v1.4.0-rc.1.')
                    ->required()
                    ->maxLength(255),
                Select::make('kind')
                    ->options(ReleaseTagKind::class)
                    ->default(ReleaseTagKind::Stable->value)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tag')
            ->columns([
                TextColumn::make('tag')
                    ->searchable(),
                TextColumn::make('kind')
                    ->badge(),
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
