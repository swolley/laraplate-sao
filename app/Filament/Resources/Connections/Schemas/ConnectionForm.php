<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Connections\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Enums\Capability;

final class ConnectionForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        return self::configureForm($schema
            ->components([
                Select::make('driver_key')
                    ->label('Driver')
                    ->options(self::driverOptions())
                    ->required()
                    ->searchable(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('base_url')
                    ->label('Base URL')
                    ->url()
                    ->maxLength(2048),
                Select::make('capabilities')
                    ->options(Capability::class)
                    ->multiple()
                    ->required()
                    ->helperText('Must be a subset of what the chosen driver declares.'),
                // The secret is write-only: never rendered back from the encrypted
                // column, and only persisted when the operator types a new value.
                KeyValue::make('credential')
                    ->label('Credential (write-only)')
                    ->keyLabel('Field')
                    ->valueLabel('Value')
                    ->afterStateHydrated(static fn (KeyValue $component): mixed => $component->state([]))
                    ->dehydrated(static fn (mixed $state): bool => filled($state))
                    ->helperText('Leave empty to keep the stored secret. Values are encrypted at rest and never shown again.'),
                TextInput::make('credential_ref')
                    ->label('Credential env reference')
                    ->helperText('Optional env/config key that overrides the stored credential.')
                    ->maxLength(255),
                KeyValue::make('config')
                    ->helperText('Non-secret driver configuration.'),
            ]));
    }

    /**
     * @return array<string, string>
     */
    private static function driverOptions(): array
    {
        $options = [];

        foreach (app(DriverRegistry::class)->all() as $driver) {
            $options[$driver->key()] = $driver->key();
        }

        return $options;
    }
}
