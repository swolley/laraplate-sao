<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderRegistryInterface;
use Modules\Core\ApplicationContent\Data\ApplicationContentAuthorization;
use Modules\Core\ApplicationContent\Data\ApplicationContentQuery;
use Modules\Core\ApplicationContent\Data\ApplicationContentResult;
use Modules\SAO\Tests\Support\ApplicationContent\EvaluationTicketCorpus;

uses(RefreshDatabase::class);

/**
 * The cases of SAO's evaluation dataset. These tests cover SAO's side of it: the
 * `sao.tickets` provider answers the cases over the corpus they were written for. Scoring
 * the answers (MRR, nDCG, the committed baseline report) belongs to whoever evaluates
 * retrieval, not to SAO.
 *
 * @return list<array<string, mixed>>
 */
function saoDatasetCases(): array
{
    $dataset = json_decode((string) file_get_contents(module_path('SAO', EvaluationTicketCorpus::DATASET)), true, flags: JSON_THROW_ON_ERROR);

    return $dataset['cases'];
}

/**
 * @param  array<string, mixed>  $case
 */
function retrieveSaoDatasetCase(array $case): ApplicationContentResult
{
    $provider = app(ApplicationContentRetrievalProviderRegistryInterface::class)->providerFor('sao.tickets');
    expect($provider)->not->toBeNull();

    return $provider->retrieve(
        new ApplicationContentQuery('sao.tickets', $case['query'], $case['locale'], $case['limit']),
        new ApplicationContentAuthorization($case['authorization']['permission'], null),
    );
}

beforeEach(function (): void {
    EvaluationTicketCorpus::create();
});

it('ranks the ticket an exact-title case expects first, citing its canonical reference', function (): void {
    $exact_cases = array_filter(saoDatasetCases(), static fn (array $case): bool => in_array('exact', $case['slices'], true));
    expect($exact_cases)->not->toBeEmpty();

    foreach ($exact_cases as $case) {
        $hits = retrieveSaoDatasetCase($case)->hits;

        expect($hits)->not->toBeEmpty()
            ->and($hits[0]->id)->toBe($case['expected_hit_ids'][0])
            ->and($hits[0]->canonicalReference)->toBe($case['expected_citation_references'][0]);
    }
});

it('returns nothing for a case the corpus cannot answer', function (): void {
    $empty_cases = array_filter(saoDatasetCases(), static fn (array $case): bool => $case['expect_authorized_empty'] === true);
    expect($empty_cases)->not->toBeEmpty();

    foreach ($empty_cases as $case) {
        expect(retrieveSaoDatasetCase($case)->hits)->toBe([]);
    }
});

it('cites every returned ticket by its SAO key, within the case limit', function (): void {
    foreach (saoDatasetCases() as $case) {
        $hits = retrieveSaoDatasetCase($case)->hits;

        expect(count($hits))->toBeLessThanOrEqual($case['limit']);

        foreach ($hits as $hit) {
            expect($hit->id)->toMatch('/^sao\.tickets:SAO-\d+$/')
                ->and($hit->canonicalReference)->toBe('/app/sao/tickets/' . mb_substr($hit->id, mb_strlen('sao.tickets:')));
        }
    }
});
