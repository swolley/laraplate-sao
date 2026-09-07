<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Scout\EngineManager;
use Modules\Core\ApplicationContent\ApplicationContentRetrievalProviderRegistry;
use Modules\Core\ApplicationContent\ApplicationContentRetrievalService;
use Modules\Core\ApplicationContent\Data\ApplicationContentAuthorization;
use Modules\Core\ApplicationContent\Data\ApplicationContentQuery;
use Modules\Core\ApplicationContent\Exceptions\ApplicationContentUnavailableException;
use Modules\Core\Casts\ActionEnum;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\DTOs\AdvancedSearchResult;
use Modules\Core\Search\Services\AdvancedSearchService;
use Modules\Core\Search\Services\EnsembleSearchService;
use Modules\Core\Search\Services\FallbackSearchPlanner;
use Modules\Core\Search\Services\SimpleQueryIntentParser;
use Modules\Core\Services\AclResolverService;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\QueryBuilder;
use Modules\Core\Support\PermissionName;
use Modules\SAO\ApplicationContent\SaoApplicationContentRetrievalProvider;
use Modules\SAO\ApplicationContent\SaoTicketEvidenceProjector;
use Modules\SAO\Database\Seeders\SAOPermissionSeeder;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\TicketQueryService;

uses(RefreshDatabase::class);

/**
 * Attaches an ACL restricting the ticket read permission to one project, gives
 * the current user a role holding it, and returns the user. Copied from
 * TicketVisibilityTest::sao_restrict_tickets_to() — the sanctioned recipe for
 * proving TicketQueryService::visible() actually hides rows.
 */
function sao_retrieval_restrict_tickets_to(Project $project): User
{
    $permission = Permission::findOrCreate(
        PermissionName::forClass(Ticket::class, ActionEnum::Select->value),
        'web',
    );

    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->getKey(),
        'filters' => new FiltersGroup([
            new Filter('project_id', $project->getKey(), FilterOperator::Equals),
        ]),
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $role = Role::query()->create(['name' => 'sao-retrieval-limited', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function sao_retrieval_authorization(): ApplicationContentAuthorization
{
    return new ApplicationContentAuthorization(
        PermissionName::forClass(Ticket::class, ActionEnum::Select->value),
        null,
    );
}

/**
 * @param  list<array{id: string, score: float, source?: array<string, mixed>}>  $hits
 * @param  array<string, mixed>  $meta
 */
function sao_retrieval_provider_with_hits(array $hits, array $meta = []): SaoApplicationContentRetrievalProvider
{
    $hits = array_map(static function (array $hit): array {
        $hit['source'] = array_merge(['connection' => 'default'], $hit['source'] ?? []);

        return $hit;
    }, $hits);

    $engine = Mockery::mock(ISearchEngine::class);
    $engine->shouldReceive('supportsOrchestratedSearch')->andReturnTrue();
    $engine->shouldReceive('supportsOrchestratedVectorSearch')->andReturnFalse();
    app(EngineManager::class)->extend('sao-application-content-test', static fn () => $engine);
    config()->set('scout.driver', 'sao-application-content-test');

    $ensemble = Mockery::mock(EnsembleSearchService::class);
    $ensemble->shouldReceive('search')->andReturn(new AdvancedSearchResult(
        hits: $hits,
        total: count($hits),
        page: 1,
        perPage: max(1, count($hits)),
        totalPages: $hits === [] ? 0 : 1,
        meta: $meta,
    ));

    $search = new AdvancedSearchService(
        new SimpleQueryIntentParser,
        new FallbackSearchPlanner,
        $ensemble,
        app(),
    );

    return new SaoApplicationContentRetrievalProvider(
        $search,
        app(TicketQueryService::class),
        app(QueryBuilder::class),
        new SaoTicketEvidenceProjector,
    );
}

function sao_retrieval_query(string $locale = 'en', int $limit = 5): ApplicationContentQuery
{
    return new ApplicationContentQuery('sao.tickets', 'deploy', $locale, $limit);
}

/**
 * Builds the real entry-point service around the given provider, so a test can
 * exercise the full path: identity + permission gate + provider + result
 * invariants, exactly as the AI assistant reaches it.
 */
function sao_retrieval_service(SaoApplicationContentRetrievalProvider $provider): ApplicationContentRetrievalService
{
    $registry = new ApplicationContentRetrievalProviderRegistry;
    $registry->register($provider);

    return new ApplicationContentRetrievalService($registry, new AuthorizationService(new AclResolverService));
}

function sao_retrieval_request(User $user): Request
{
    Auth::login($user);
    $request = Request::create('/app/ai/messages', 'POST');
    $request->setUserResolver(static fn (): User => $user);

    return $request;
}

it('returns ACL-authorized ticket evidence ranked by the engine', function (): void {
    $this->seed(SAOPermissionSeeder::class);

    $mine = Project::factory()->create(['key_prefix' => 'MINE']);
    $theirs = Project::factory()->create(['key_prefix' => 'THRS']);

    $visible = Ticket::factory()->forProject($mine)->create(['title' => 'Deploy pipeline fix']);
    $hidden = Ticket::factory()->forProject($theirs)->create(['title' => 'Deploy secret ticket']);

    $this->actingAs(sao_retrieval_restrict_tickets_to($mine));

    $provider = sao_retrieval_provider_with_hits([
        ['id' => (string) $hidden->getKey(), 'score' => 0.99],
        ['id' => (string) $visible->getKey(), 'score' => 0.75],
    ], ['strategies' => ['keyword']]);

    $result = $provider->retrieve(sao_retrieval_query(), sao_retrieval_authorization());

    $keys = array_map(static fn ($hit) => $hit->recordKey, $result->hits);

    expect($keys)->toContain($visible->key)
        ->and($keys)->not->toContain($hidden->key)
        ->and($result->source)->toBe('sao.tickets');
});

it('falls back to a lexical DB search over visible() when the engine returns nothing', function (): void {
    $this->seed(SAOPermissionSeeder::class);

    $mine = Project::factory()->create(['key_prefix' => 'MINE']);
    $theirs = Project::factory()->create(['key_prefix' => 'THRS']);

    $visible = Ticket::factory()->forProject($mine)->create(['title' => 'Deploy runbook update']);
    Ticket::factory()->forProject($theirs)->create(['title' => 'Deploy secret runbook']);

    $this->actingAs(sao_retrieval_restrict_tickets_to($mine));

    $provider = sao_retrieval_provider_with_hits([], ['strategies' => ['keyword']]);

    $result = $provider->retrieve(sao_retrieval_query(), sao_retrieval_authorization());

    expect($result->hits)->not->toBe([])
        ->and($result->strategy)->toBe('lexical')
        ->and(array_map(static fn ($hit) => $hit->recordKey, $result->hits))->toBe([$visible->key]);
});

it('never returns a ticket outside visible() even if the engine matches it', function (): void {
    $this->seed(SAOPermissionSeeder::class);

    $mine = Project::factory()->create(['key_prefix' => 'MINE']);
    $theirs = Project::factory()->create(['key_prefix' => 'THRS']);

    Ticket::factory()->forProject($mine)->create(['title' => 'Deploy pipeline fix']);
    $hidden = Ticket::factory()->forProject($theirs)->create(['title' => 'Deploy secret ticket']);

    $this->actingAs(sao_retrieval_restrict_tickets_to($mine));

    $provider = sao_retrieval_provider_with_hits([
        ['id' => (string) $hidden->getKey(), 'score' => 0.9],
    ], ['strategies' => ['keyword']]);

    $result = $provider->retrieve(sao_retrieval_query(), sao_retrieval_authorization());

    expect($result->hits)->toBe([]);
});

it('serves ACL-authorized ticket evidence to a non-superadmin through the retrieval service', function (): void {
    $this->seed(SAOPermissionSeeder::class);
    config()->set('app.locale', 'en');

    $mine = Project::factory()->create(['key_prefix' => 'MINE']);
    $theirs = Project::factory()->create(['key_prefix' => 'THRS']);

    $visible = Ticket::factory()->forProject($mine)->create(['title' => 'Deploy pipeline fix']);
    $hidden = Ticket::factory()->forProject($theirs)->create(['title' => 'Deploy secret ticket']);

    $user = sao_retrieval_restrict_tickets_to($mine);
    $provider = sao_retrieval_provider_with_hits([
        ['id' => (string) $hidden->getKey(), 'score' => 0.99],
        ['id' => (string) $visible->getKey(), 'score' => 0.75],
    ], ['strategies' => ['keyword']]);

    // Before the model-table fix, the service gate checked `default.tickets.select`
    // (the short entity), which no role holds, so this non-superadmin was denied
    // here even though they hold `default.sao_tickets.select`.
    $result = sao_retrieval_service($provider)->retrieve(sao_retrieval_request($user), sao_retrieval_query());

    $keys = array_map(static fn ($hit) => $hit->recordKey, $result->hits);

    expect($keys)->toContain($visible->key)
        ->and($keys)->not->toContain($hidden->key)
        ->and($result->source)->toBe('sao.tickets');
});

it('denies a non-superadmin lacking the ticket select permission at the service gate', function (): void {
    $this->seed(SAOPermissionSeeder::class);
    config()->set('app.locale', 'en');

    $project = Project::factory()->create(['key_prefix' => 'MINE']);
    $ticket = Ticket::factory()->forProject($project)->create(['title' => 'Deploy pipeline fix']);

    $provider = sao_retrieval_provider_with_hits([
        ['id' => (string) $ticket->getKey(), 'score' => 0.9],
    ], ['strategies' => ['keyword']]);

    expect(fn () => sao_retrieval_service($provider)->retrieve(
        sao_retrieval_request(User::factory()->create()),
        sao_retrieval_query(),
    ))->toThrow(ApplicationContentUnavailableException::class);
});
