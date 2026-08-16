<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Core\Models\User;
use Modules\SAO\Drivers\External\GitHubDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\OwnershipRule;
use Modules\SAO\Models\ContributorIdentity;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\OwnershipSuggestionCoordinator;

uses(RefreshDatabase::class);

/**
 * A GitHub stand-in serving all three evidence sources: a CODEOWNERS file, a
 * commits feed and a GraphQL blame response, all attributing the same touched
 * area — so the coordinator's precedence (CODEOWNERS wins) can be observed.
 */
function fakeOwnershipRepo(): void
{
    Http::fake(function (Request $request) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $method = $request->method();

        if ($path === '/repos/acme/widgets/contents/CODEOWNERS') {
            return Http::response(['content' => base64_encode("app/Billing/ @billing\n"), 'encoding' => 'base64']);
        }

        if (str_starts_with($path, '/repos/acme/widgets/contents/')) {
            return Http::response(['message' => 'Not Found'], 404);
        }

        if ($path === '/repos/acme/widgets/commits' && $method === 'GET') {
            $page = (int) ($query['page'] ?? 1);
            $all = array_map(static fn (int $n): array => [
                'sha' => 'c' . $n,
                'commit' => ['message' => 'c' . $n, 'author' => ['name' => 'Octo', 'email' => 'octo@example.com']],
                'author' => ['login' => 'octocat'],
                'html_url' => "https://github.com/acme/widgets/commit/c{$n}",
            ], range(1, 2));
            $slice = $page === 1 ? $all : [];

            return Http::response($slice, 200);
        }

        if ($path === '/graphql' && $method === 'POST') {
            return Http::response(['data' => ['repository' => ['object' => ['blame' => ['ranges' => [
                ['startingLine' => 1, 'endingLine' => 40, 'commit' => ['author' => ['email' => 'octo@example.com', 'user' => ['login' => 'octocat']]]],
            ]]]]]]);
        }

        return Http::response(['message' => 'Unhandled'], 500);
    });
}

function ownershipContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(baseUrl: 'https://api.github.com', credentials: ['token' => 'ghp_secret']),
        remoteIdentifier: 'acme/widgets',
        config: ['page_size' => 30],
    );
}

test('the coordinator gathers evidence, applies precedence and persists the winning suggestion', function (): void {
    fakeOwnershipRepo();

    $ticket = Ticket::factory()->create();
    $billing = User::factory()->create();
    $octo = User::factory()->create();

    ContributorIdentity::factory()->anyProvider()->create(['identity' => '@billing', 'user_id' => $billing->id]);
    ContributorIdentity::factory()->forProvider('github')->create(['identity' => 'octocat', 'user_id' => $octo->id]);

    $suggestion = app(OwnershipSuggestionCoordinator::class)->suggestFor(
        $ticket,
        new GitHubDriver,
        ownershipContext(),
        'github',
        ['app/Billing/Invoice.php'],
        'main',
    );

    // Blame (40 lines) and recent-touch (2 commits) both point at octocat, but
    // the explicit CODEOWNERS entry outranks them regardless of score.
    expect($suggestion)->not->toBeNull()
        ->and($suggestion->suggested_user_id)->toBe($billing->id)
        ->and($suggestion->rule)->toBe(OwnershipRule::Codeowners)
        ->and($suggestion->ticket_id)->toBe($ticket->id);
});

test('with no resolvable evidence the coordinator persists nothing', function (): void {
    fakeOwnershipRepo();

    $ticket = Ticket::factory()->create();

    $suggestion = app(OwnershipSuggestionCoordinator::class)->suggestFor(
        $ticket,
        new GitHubDriver,
        ownershipContext(),
        'github',
        ['app/Billing/Invoice.php'],
        'main',
    );

    expect($suggestion)->toBeNull()
        ->and($ticket->fresh()->assignee_id)->toBeNull();
});
