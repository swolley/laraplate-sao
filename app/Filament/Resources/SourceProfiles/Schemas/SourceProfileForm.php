<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\SourceProfiles\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;

final class SourceProfileForm
{
    use HasForm;

    /**
     * The matcher operators {@see \Modules\SAO\Ingest\PayloadMatcher} understands.
     * `exists` ignores the value; `equals`/`contains` compare against it.
     *
     * @var array<string, string>
     */
    private const array MATCHER_OPERATORS = [
        'equals' => 'equals',
        'exists' => 'exists (value ignored)',
        'contains' => 'contains',
    ];

    public static function configure(Schema $schema): Schema
    {
        return self::configureForm($schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                Repeater::make('matchers')
                    ->helperText('All matchers must pass (AND) for the profile to apply to a payload.')
                    ->schema([
                        TextInput::make('path')
                            ->label('Payload path')
                            ->helperText('Dot-path into the payload, e.g. error.type.')
                            ->required(),
                        Select::make('operator')
                            ->options(self::MATCHER_OPERATORS)
                            ->default('equals')
                            ->required(),
                        TextInput::make('value')
                            ->helperText('Compared for equals/contains; ignored for exists.'),
                    ])
                    ->addActionLabel('Add matcher')
                    ->columns(3),
                KeyValue::make('field_bindings')
                    ->keyLabel('Canonical field')
                    ->valueLabel('Payload path')
                    ->helperText('Map a canonical field to a payload dot-path, e.g. message = error.message.')
                    ->required(),
            ]));
    }
}
