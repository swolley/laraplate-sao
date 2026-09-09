<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Scout\ModelObserver;
use Modules\AI\Services\ApplicationContent\Evaluation\ApplicationContentEvaluationDataset;
use Modules\AI\Services\ApplicationContent\Evaluation\ApplicationContentEvaluationService;
use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderRegistryInterface;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;

uses(RefreshDatabase::class);

/**
 * Seeds the deterministic 8-ticket dev corpus (`SAO-1..SAO-8`), mirroring
 * `DevSAODatabaseSeeder`'s project + title order. `description` is pinned to
 * an empty string (the seeder leaves it to the factory's random paragraph)
 * so the lexical fallback's `title OR description LIKE` search never matches
 * on non-deterministic Faker text, keeping the baseline reproducible.
 *
 * @return list<Ticket>
 */
function createSaoEvaluationTickets(): array
{
    $project = Project::factory()->create(['key_prefix' => 'SAO']);

    $titles = [
        'Login non funziona su Safari',
        'Aggiungere export CSV allo storico',
        'Migliorare accessibilità dei form',
        'Timeout intermittente API pagamenti',
        'Refactor modulo notifiche',
        'In attesa credenziali fornitore',
        'Fix typo nella homepage',
        'Aggiornare dipendenze minori',
    ];

    ModelObserver::disableSyncingFor(Ticket::class);

    try {
        return array_map(
            static fn (string $title): Ticket => Ticket::factory()->forProject($project)->create([
                'title' => $title,
                'description' => '',
            ]),
            $titles,
        );
    } finally {
        ModelObserver::enableSyncingFor(Ticket::class);
    }
}

it('reproduces the committed record-level baseline from generated SAO tickets', function (): void {
    createSaoEvaluationTickets();

    $dataset = ApplicationContentEvaluationDataset::fromFile(
        module_path('SAO', 'tests/Fixtures/application-content/sao-tickets.json'),
    );
    $provider = app(ApplicationContentRetrievalProviderRegistryInterface::class)->providerFor('sao.tickets');
    expect($provider)->not->toBeNull();

    $tick = 0.0;
    $evaluation = new ApplicationContentEvaluationService(
        clock: static function () use (&$tick): float {
            $current = $tick;
            $tick += 0.01;

            return $current;
        },
    );
    $report = $evaluation->evaluate(
        $dataset,
        'sao.tickets',
        'database-generated-fixture',
        static fn ($query, $authorization) => $provider->retrieve($query, $authorization),
    );
    $artifact_path = module_path('SAO', 'docs/evaluations/application-content/2026-09-record-baseline.json');

    if (getenv('APP_CONTENT_BASELINE_REGEN') === '1') {
        file_put_contents($artifact_path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) . "\n");
    }

    $artifact = json_decode((string) file_get_contents($artifact_path), true, flags: JSON_THROW_ON_ERROR);

    expect($report)->toBe($artifact);
});
