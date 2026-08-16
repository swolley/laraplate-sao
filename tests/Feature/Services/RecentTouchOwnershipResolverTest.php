<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Drivers\External\GitHubDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\OwnershipRule;
use Modules\SAO\Services\RecentTouchOwnershipResolver;

/**
 * A paginated GitHub commits feed with mixed authorship: three commits by
 * @octocat, one by @hopper, and one with no linked account that carries only a
 * git author email — enough to exercise counting, the email fallback, and
 * pagination.
 *
 * @param  list<array{login: ?string, email: string}>  $authors
 */
function fakeRecentTouchRepo(array $authors): void
{
    Http::fake(function (Request $request) use ($authors) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        if ($path !== '/repos/acme/widgets/commits') {
            return Http::response(['message' => 'Unhandled'], 500);
        }

        $page = (int) ($query['page'] ?? 1);
        $perPage = (int) ($query['per_page'] ?? 30);

        $all = [];
        foreach ($authors as $i => $author) {
            $n = $i + 1;
            $all[] = [
                'sha' => 'c' . $n,
                'commit' => ['message' => 'commit ' . $n, 'author' => ['name' => 'Dev ' . $n, 'email' => $author['email']]],
                'author' => $author['login'] === null ? null : ['login' => $author['login']],
                'html_url' => "https://github.com/acme/widgets/commit/c{$n}",
            ];
        }

        $slice = array_slice($all, ($page - 1) * $perPage, $perPage);
        $headers = $page * $perPage < count($all)
            ? ['Link' => '<https://api.github.com/repos/acme/widgets/commits?page=' . ($page + 1) . '>; rel="next"']
            : [];

        return Http::response(array_values($slice), 200, $headers);
    });
}

function recentTouchContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(baseUrl: 'https://api.github.com', credentials: ['token' => 'ghp_secret']),
        remoteIdentifier: 'acme/widgets',
        config: ['page_size' => 2],
    );
}

const RECENT_TOUCH_AUTHORS = [
    ['login' => 'octocat', 'email' => 'octo@example.com'],
    ['login' => 'octocat', 'email' => 'octo@example.com'],
    ['login' => 'hopper', 'email' => 'grace@example.com'],
    ['login' => null, 'email' => 'ada@example.com'],
    ['login' => 'octocat', 'email' => 'octo@example.com'],
];

test('recent-touch counts commits per author across pages and resolves via the identity map', function (): void {
    fakeRecentTouchRepo(RECENT_TOUCH_AUTHORS);
    $resolver = new RecentTouchOwnershipResolver();

    $evidence = $resolver->resolve(
        new GitHubDriver,
        recentTouchContext(),
        'main',
        ['octocat' => 10, 'ada@example.com' => 30], // @hopper is unmapped, so skipped.
    );

    $byUser = collect($evidence)->keyBy(fn ($e): int => $e->userId);

    expect($byUser->keys()->sort()->values()->all())->toBe([10, 30])
        ->and($byUser[10]->rule)->toBe(OwnershipRule::RecentTouch)
        ->and($byUser[10]->score)->toBe(3.0)
        ->and($byUser[10]->detail['identity'])->toBe('octocat')
        // The unlinked commit falls back to its git author email.
        ->and($byUser[30]->score)->toBe(1.0)
        ->and($byUser[30]->detail['identity'])->toBe('ada@example.com');
});

test('maxCommits bounds how far back the count reaches', function (): void {
    fakeRecentTouchRepo(RECENT_TOUCH_AUTHORS);
    $resolver = new RecentTouchOwnershipResolver();

    $evidence = $resolver->resolve(new GitHubDriver, recentTouchContext(), 'main', ['octocat' => 10], maxCommits: 2);

    $byUser = collect($evidence)->keyBy(fn ($e): int => $e->userId);

    // Only the first two commits (both octocat) are counted.
    expect($byUser[10]->score)->toBe(2.0);
});

test('no resolvable authors yields no evidence', function (): void {
    fakeRecentTouchRepo(RECENT_TOUCH_AUTHORS);
    $resolver = new RecentTouchOwnershipResolver();

    expect($resolver->resolve(new GitHubDriver, recentTouchContext(), 'main', ['nobody' => 1]))->toBe([]);
});
