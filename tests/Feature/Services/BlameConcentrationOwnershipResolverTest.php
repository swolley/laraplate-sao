<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Drivers\External\GitHubDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\OwnershipRule;
use Modules\SAO\Services\BlameConcentrationOwnershipResolver;

/**
 * Fakes the GitHub GraphQL blame endpoint, returning different ranges per file
 * (read from the query's `path` variable) so cross-file line aggregation is
 * exercised.
 */
function fakeBlameByPath(): void
{
    $rangesByPath = [
        'app/A.php' => [
            ['startingLine' => 1, 'endingLine' => 10, 'login' => 'octocat', 'email' => 'octo@example.com'],
        ],
        'app/B.php' => [
            ['startingLine' => 1, 'endingLine' => 5, 'login' => 'octocat', 'email' => 'octo@example.com'],
            ['startingLine' => 6, 'endingLine' => 13, 'login' => 'hopper', 'email' => 'grace@example.com'],
        ],
    ];

    Http::fake(function (Request $request) use ($rangesByPath) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';

        if ($path !== '/graphql') {
            return Http::response(['message' => 'Unhandled'], 500);
        }

        $wanted = $request->data()['variables']['path'] ?? '';
        $ranges = array_map(static fn (array $r): array => [
            'startingLine' => $r['startingLine'],
            'endingLine' => $r['endingLine'],
            'commit' => ['author' => ['email' => $r['email'], 'user' => ['login' => $r['login']]]],
        ], $rangesByPath[$wanted] ?? []);

        return Http::response(['data' => ['repository' => ['object' => ['blame' => ['ranges' => $ranges]]]]]);
    });
}

function blameContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(baseUrl: 'https://api.github.com', credentials: ['token' => 'ghp_secret']),
        remoteIdentifier: 'acme/widgets',
    );
}

test('blame concentration sums owned lines per author across the touched files', function (): void {
    fakeBlameByPath();
    $resolver = new BlameConcentrationOwnershipResolver();

    $evidence = $resolver->resolve(
        new GitHubDriver,
        blameContext(),
        ['app/A.php', 'app/B.php'],
        'main',
        ['octocat' => 10, 'hopper' => 20],
    );

    $byUser = collect($evidence)->keyBy(fn ($e): int => $e->userId);

    expect($byUser->keys()->sort()->values()->all())->toBe([10, 20])
        ->and($byUser[10]->rule)->toBe(OwnershipRule::BlameConcentration)
        // 10 lines in A + 5 in B.
        ->and($byUser[10]->score)->toBe(15.0)
        ->and($byUser[10]->paths)->toBe(['app/A.php', 'app/B.php'])
        ->and($byUser[20]->score)->toBe(8.0)
        ->and($byUser[20]->paths)->toBe(['app/B.php']);
});

test('unmapped blame identities are skipped', function (): void {
    fakeBlameByPath();
    $resolver = new BlameConcentrationOwnershipResolver();

    $evidence = $resolver->resolve(new GitHubDriver, blameContext(), ['app/B.php'], 'main', ['hopper' => 20]);

    $byUser = collect($evidence)->keyBy(fn ($e): int => $e->userId);

    // octocat is unmapped, so only hopper survives.
    expect($byUser->keys()->all())->toBe([20])
        ->and($byUser[20]->score)->toBe(8.0);
});
