<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\ClosurePolicies\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;
use Modules\SAO\Enums\ClosureAction;

final class ClosurePolicyForm
{
    use HasForm;

    /**
     * The closure condition keys an operator can compose, mirroring
     * {@see \Modules\SAO\Closure\ClosureConditionRegistry}. Duration-based ones
     * (`no_recurrence_for`, `resolved_for`) take a `days` entry in their config.
     *
     * @var array<string, string>
     */
    private const array CONDITION_KEYS = [
        'pull_request_merged' => 'Pull request merged',
        'no_recurrence_for' => 'No recurrence for (config: days)',
        'fix_released' => 'Fix released (shipped only)',
        'fix_deployed_there' => 'Fix deployed there',
        'resolved_for' => 'Resolved for (config: days)',
        'internal_tickets_only' => 'Internal tickets only',
    ];

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
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('action')
                    ->options(ClosureAction::class)
                    ->default(ClosureAction::Propose->value)
                    ->required()
                    ->helperText('Propose is the prudent default; Close acts automatically.'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                Repeater::make('conditions')
                    ->helperText('All conditions must hold (AND). An empty set never closes.')
                    ->schema([
                        Select::make('key')
                            ->options(self::CONDITION_KEYS)
                            ->required(),
                        KeyValue::make('config')
                            ->keyLabel('Setting')
                            ->valueLabel('Value')
                            ->helperText('e.g. days = 14 for the duration-based conditions.'),
                    ])
                    ->addActionLabel('Add condition')
                    ->columns(1),
            ]));
    }
}
