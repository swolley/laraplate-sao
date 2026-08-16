<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Drivers\External\GitHubDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\OwnershipRule;
use Modules\SAO\Services\CodeownersOwnershipResolver;

/**
 * Fakes the GitHub contents endpoint so the driver's fileAtRef reads a
 * base64-encoded CODEOWNERS; every other content path is a 404.
 */
function fakeCodeownersRepo(string $codeowners): void
{
    Http::fake(function (Request $request) use ($codeowners) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';

        if ($path === '/repos/acme/widgets/contents/CODEOWNERS') {
            return Http::response(['content' => base64_encode($codeowners), 'encoding' => 'base64']);
        }

        if (str_starts_with($path, '/repos/acme/widgets/contents/')) {
            return Http::response(['message' => 'Not Found'], 404);
        }

        return Http::response(['message' => 'Unhandled'], 500);
    });
}

function codeownersContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(baseUrl: 'https://api.github.com', credentials: ['token' => 'ghp_secret']),
        remoteIdentifier: 'acme/widgets',
    );
}

const CODEOWNERS_FIXTURE = <<<'TXT'
# owners
*                     @default-owner
app/                  @backend-team
app/Billing/*.php     @billing-lead
docs/                 @docs-team
TXT;

test('codeowners evidence maps touched paths to resolvable owners, last pattern winning', function (): void {
    fakeCodeownersRepo(CODEOWNERS_FIXTURE);
    $resolver = new CodeownersOwnershipResolver();

    $evidence = $resolver->resolve(
        new GitHubDriver,
        codeownersContext(),
        'main',
        ['app/Billing/Invoice.php', 'app/Billing/Payment.php', 'app/Http/Kernel.php', 'README.md'],
        ['@billing-lead' => 10, '@backend-team' => 20],
    );

    $byUser = collect($evidence)->keyBy(fn ($e): int => $e->userId);

    // @default-owner (README.md) is dropped: not in the identity map.
    expect($byUser->keys()->sort()->values()->all())->toBe([10, 20])
        ->and($byUser[10]->rule)->toBe(OwnershipRule::Codeowners)
        ->and($byUser[10]->score)->toBe(2.0)
        ->and($byUser[10]->paths)->toBe(['app/Billing/Invoice.php', 'app/Billing/Payment.php'])
        ->and($byUser[10]->detail['handle'])->toBe('@billing-lead')
        ->and($byUser[20]->paths)->toBe(['app/Http/Kernel.php']);
});

test('no CODEOWNERS file yields no evidence', function (): void {
    fakeCodeownersRepo('');
    $resolver = new CodeownersOwnershipResolver();

    $evidence = $resolver->resolve(new GitHubDriver, codeownersContext(), 'main', ['app/A.php'], ['@x' => 1]);

    expect($evidence)->toBe([]);
});

test('unresolvable handles are skipped entirely', function (): void {
    fakeCodeownersRepo(CODEOWNERS_FIXTURE);
    $resolver = new CodeownersOwnershipResolver();

    $evidence = $resolver->resolve(new GitHubDriver, codeownersContext(), 'main', ['README.md'], []);

    expect($evidence)->toBe([]);
});
