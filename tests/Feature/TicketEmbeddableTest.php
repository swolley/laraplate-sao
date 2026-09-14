<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Scout\EngineManager;
use Modules\AI\Ai\Embeddings\EmbeddingModelRegistry;
use Modules\AI\Contracts\IEmbeddingService;
use Modules\AI\Jobs\GenerateEmbeddingsJob;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\SAO\Models\Ticket;
use NeuronAI\RAG\Document;

uses(RefreshDatabase::class);

/**
 * Builds a NeuronAI Document carrying the given embedding vector, the shape
 * IEmbeddingService::embedDocument() returns. Mirrors the helper in AI's
 * GenerateEmbeddingsPerLocaleTest.
 *
 * @param  list<float>  $vector
 */
function ticketEmbeddingDocument(array $vector): Document
{
    $document = new Document('');
    $document->embedding = $vector;

    return $document;
}

it('declares title and description as the embeddable fields', function (): void {
    expect((new Ticket())->getEmbedFields())->toBe(['title', 'description']);
});

it('generates exactly one locale-null embedding row stamped with the active model key', function (): void {
    $ticket = Ticket::factory()->create([
        'title' => 'Deploy failed on staging',
        'description' => 'The pipeline went red after the last release.',
    ]);

    $embedding_service = Mockery::mock(IEmbeddingService::class);
    $embedding_service->shouldReceive('embedDocument')
        ->once()
        ->with('Deploy failed on staging The pipeline went red after the last release.')
        ->andReturn([ticketEmbeddingDocument([0.1, 0.2, 0.3])]);

    $job = new GenerateEmbeddingsJob($ticket);
    $job->handle($embedding_service);

    $rows = $ticket->embeddings()->get();
    $expected_key = app(EmbeddingModelRegistry::class)->active()->key;

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->locale)->toBeNull()
        ->and($rows->first()->model_key)->toBe($expected_key)
        ->and($rows->first()->embedding)->toBe([0.1, 0.2, 0.3]);
});

it('emits a single agnostic embeddings entry in the search document', function (): void {
    // Create the ticket first under the suite's default Scout driver — swapping the driver
    // beforehand would route the factory's own save-triggered indexing through the fake
    // engine below, which stubs only what this test needs (supportsVectorSearch).
    $ticket = Ticket::factory()->create([
        'title' => 'Deploy failed on staging',
        'description' => 'The pipeline went red after the last release.',
    ]);

    // Ticket is now embeddable, so saving it for real already ran the app's default
    // (local, non-HTTP) embedding pipeline synchronously (QUEUE_CONNECTION=sync in
    // phpunit.xml). Replace whatever it produced with one deterministic row so this
    // test asserts the document shape, not that pipeline's own output.
    $ticket->embeddings()->delete();
    $ticket->embeddings()->create([
        'embedding' => [0.1, 0.2, 0.3],
        'locale' => null,
        'model_key' => app(EmbeddingModelRegistry::class)->active()->key,
    ]);

    // The suite's default 'collection' Scout driver does not implement ISearchEngine, so
    // toSearchableArray() would never reach the embeddings branch. Register a fake engine
    // under a throwaway driver name instead, mirroring
    // SaoApplicationContentRetrievalProviderTest's `sao-application-content-test` pattern.
    Config::set('search.vector_search.enabled', true);
    $engine = Mockery::mock(ISearchEngine::class);
    $engine->shouldReceive('supportsVectorSearch')->andReturnTrue();
    app(EngineManager::class)->extend('ticket-embeddable-test', static fn () => $engine);
    Config::set('scout.driver', 'ticket-embeddable-test');

    $document = $ticket->fresh()->toSearchableArray();

    expect($document['embeddings'])->toBe([['vector' => [0.1, 0.2, 0.3]]]);
});

it('declares embeddings as a nested vector field in the search mapping, unconditionally', function (): void {
    // Force the Elasticsearch translator so the mapping shape can be asserted without
    // depending on which Scout driver the environment happens to have configured,
    // mirroring ContentSearchableArrayTest's mapping assertion.
    Config::set('scout.driver', 'elasticsearch');

    // A brand-new, unsaved Ticket has no ModelEmbedding rows, so toSearchableArray()
    // would omit the `embeddings` key entirely — the mapping must still declare the
    // field, since an index is normally created before any ticket has been embedded.
    $mapping = (new Ticket())->getSearchMapping();
    $properties = $mapping['mappings']['properties'];

    expect($properties['embeddings']['type'])->toBe('nested')
        ->and($properties['embeddings']['properties']['vector']['type'])->toBe('dense_vector')
        ->and($properties['embeddings']['properties']['vector']['dims'])->toBe((int) config('search.vector.dimensions', 384))
        ->and($properties['title']['type'])->toBe('text');
});
