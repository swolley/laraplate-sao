<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Signals\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;
use Modules\SAO\Enums\SignalState;

/**
 * A signal is machine-managed: its fingerprint, counters and timestamps are
 * read-only here. The one operator-editable field is the state (resolve, mute,
 * archive) — reopening is left to the ingest pipeline on recurrence.
 */
final class SignalForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        return self::configureForm($schema
            ->components([
                Select::make('state')
                    ->options(SignalState::class)
                    ->required(),
                TextInput::make('group_key')
                    ->label('Group key')
                    ->disabled(),
                TextInput::make('algo_version')
                    ->label('Algorithm version')
                    ->disabled(),
                TextInput::make('occurrence_count')
                    ->label('Occurrences')
                    ->disabled(),
                TextInput::make('first_seen_at')
                    ->label('First seen')
                    ->disabled(),
                TextInput::make('last_seen_at')
                    ->label('Last seen')
                    ->disabled(),
            ]));
    }
}
