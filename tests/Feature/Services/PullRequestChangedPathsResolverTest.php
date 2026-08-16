<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\SAO\Drivers\External\GitHubDriver;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Enums\ChangeRefType;
use Modules\SAO\Models\ChangeRef;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\PullRequestChangedPathsResolver;

uses(RefreshDatabase::class);

function fakeCompare(array $files): void
{
    Http::fake(function (Request $request) use ($files) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';

        if (str_starts_with($path, '/repos/acme/widgets/compare/')) {
            return Http::response(['status' => 'ahead', 'files' => $files]);
        }

        return Http::response(['message' => 'Unhandled'], 500);
    });
}

function prChangedContext(): BindingContext
{
    return new BindingContext(
        new ConnectionContext(baseUrl: 'https://api.github.com', credentials: ['token' => 'ghp_secret']),
        remoteIdentifier: 'acme/widgets',
    );
}

test('it extracts the changed file paths of a pull request from a compare', function (): void {
    fakeCompare([
        ['filename' => 'app/Billing/Invoice.php'],
        ['filename' => 'app/Billing/Payment.php'],
        ['filename' => 'app/Billing/Invoice.php'], // a duplicate is collapsed
    ]);

    $pr = ChangeRef::factory()->create([
        'type' => ChangeRefType::PullRequest,
        'identifier' => '42',
        'base_ref' => 'main',
        'head_ref' => 'feature/x',
    ]);

    $paths = (new PullRequestChangedPathsResolver())->resolve(new GitHubDriver, prChangedContext(), $pr);

    expect($paths)->toBe(['app/Billing/Invoice.php', 'app/Billing/Payment.php']);
});

test('a non-pull-request reference or one missing its refs yields no paths', function (): void {
    fakeCompare([['filename' => 'app/A.php']]);
    $resolver = new PullRequestChangedPathsResolver();

    $commit = ChangeRef::factory()->create(['type' => ChangeRefType::Commit, 'identifier' => 'abc']);
    $bareRef = ChangeRef::factory()->create([
        'type' => ChangeRefType::PullRequest,
        'identifier' => '7',
        'base_ref' => null,
        'head_ref' => null,
    ]);

    expect($resolver->resolve(new GitHubDriver, prChangedContext(), $commit))->toBe([])
        ->and($resolver->resolve(new GitHubDriver, prChangedContext(), $bareRef))->toBe([]);
});
