<?php

declare(strict_types=1);

namespace Modules\SAO\Console;

use Illuminate\Console\Command;
use Modules\SAO\Ingest\IngestReplayService;
use Modules\SAO\Models\IngestEvent;
use Modules\SAO\Models\SourceProfile;

/**
 * Dry-run a stored ingest event against a source profile: it shows what the
 * pipeline *would* do (match, canonical fields, correlation, would-be status)
 * without writing anything, so an operator can tune a profile against a real
 * payload before making it live. Defaults to the event's recorded profile;
 * `--profile` replays against a different one.
 */
final class ReplayIngestEventCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sao:ingest:replay {event : The IngestEvent id to replay} {--profile= : SourceProfile id to replay against; defaults to the event\'s recorded profile}';

    /**
     * @var string
     */
    protected $description = 'Dry-run an ingest event against a source profile without writing anything';

    public function handle(IngestReplayService $service): int
    {
        $event = IngestEvent::query()->find($this->argument('event'));

        if (! $event instanceof IngestEvent) {
            $this->error("No ingest event with id [{$this->argument('event')}].");

            return self::FAILURE;
        }

        $profile_id = $this->option('profile') ?? $event->source_profile_id;

        if ($profile_id === null) {
            $this->warn('The event has no recorded profile; pass --profile to choose one.');

            return self::FAILURE;
        }

        $profile = SourceProfile::query()->find($profile_id);

        if (! $profile instanceof SourceProfile) {
            $this->error("No source profile with id [{$profile_id}].");

            return self::FAILURE;
        }

        $result = $service->dryRun($event, $profile);

        $this->table(['Field', 'Value'], [
            ['profile', $profile->name],
            ['matches', $result['matches'] ? 'yes' : 'no'],
            ['would_be_status', $result['would_be_status']],
            ['project_id', $result['project_id'] === null ? '—' : (string) $result['project_id']],
            ['winning_rule', $result['winning_rule'] ?? '—'],
        ]);

        if ($result['canonical'] !== []) {
            $this->line('Canonical fields:');
            $this->table(['Key', 'Value'], array_map(
                static fn (string $key, mixed $value): array => [
                    $key,
                    is_scalar($value) ? (string) $value : (string) json_encode($value),
                ],
                array_keys($result['canonical']),
                array_values($result['canonical']),
            ));
        }

        return self::SUCCESS;
    }
}
