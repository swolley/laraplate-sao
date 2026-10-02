<?php

declare(strict_types=1);

namespace Modules\SAO\Tests\Support\ApplicationContent;

use Laravel\Scout\ModelObserver;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;

/**
 * The deterministic 8-ticket corpus (`SAO-1..SAO-8`) behind the `sao.tickets` evaluation
 * dataset in `tests/Fixtures/application-content/sao-tickets.json`, mirroring
 * `DevSAODatabaseSeeder`'s project and title order. `description` is pinned to an empty
 * string (the seeder leaves it to the factory's random paragraph) so the lexical
 * fallback's `title OR description LIKE` search never matches on non-deterministic
 * Faker text.
 *
 * Shared by SAO's own dataset test and by the application's cross-module evaluation
 * baseline, so both always measure the same corpus.
 */
final class EvaluationTicketCorpus
{
    public const string DATASET = 'tests/Fixtures/application-content/sao-tickets.json';

    private const array TITLES = [
        'Login non funziona su Safari',
        'Aggiungere export CSV allo storico',
        'Migliorare accessibilità dei form',
        'Timeout intermittente API pagamenti',
        'Refactor modulo notifiche',
        'In attesa credenziali fornitore',
        'Fix typo nella homepage',
        'Aggiornare dipendenze minori',
    ];

    /**
     * @return list<Ticket>
     */
    public static function create(): array
    {
        $project = Project::factory()->create(['key_prefix' => 'SAO']);

        ModelObserver::disableSyncingFor(Ticket::class);

        try {
            return array_map(
                static fn (string $title): Ticket => Ticket::factory()->forProject($project)->create([
                    'title' => $title,
                    'description' => '',
                ]),
                self::TITLES,
            );
        } finally {
            ModelObserver::enableSyncingFor(Ticket::class);
        }
    }
}
