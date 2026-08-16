<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Releases\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;
use Modules\SAO\Enums\ReleaseStatus;

final class ReleaseForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        return self::configureForm($schema
            ->components([
                Select::make('project_id')
                    ->label('Project')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('version')
                    ->helperText('The stable label the release is named as, e.g. 1.4.0.')
                    ->required()
                    ->maxLength(255),
                Select::make('status')
                    ->options(ReleaseStatus::class)
                    ->default(ReleaseStatus::Announced->value)
                    ->required(),
                DateTimePicker::make('released_at')
                    ->label('Released at')
                    ->helperText('Set when a stable tag first realizes the release.'),
            ]));
    }
}
