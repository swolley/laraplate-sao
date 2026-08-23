<?php

declare(strict_types=1);

namespace Modules\SAO\Filament\Resources\Deployments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\SAO\Models\Deployment;

final class DeploymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('project.name')
                    ->label('Project'),
                TextEntry::make('version'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('environment.name')
                    ->label('Environment')
                    ->placeholder('—'),
                TextEntry::make('release.version')
                    ->label('Release')
                    ->placeholder('—'),
                TextEntry::make('connection.name')
                    ->label('Connection')
                    ->placeholder('—'),
                TextEntry::make('external_id')
                    ->label('External id')
                    ->placeholder('—'),
                TextEntry::make('started_at')
                    ->dateTime(),
                TextEntry::make('finished_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('meta')
                    ->label('Meta')
                    ->columnSpanFull()
                    ->placeholder('—')
                    ->state(static fn (Deployment $record): ?string => $record->meta === null
                        ? null
                        : (string) json_encode($record->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            ]);
    }
}
