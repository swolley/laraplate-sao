<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\ReleaseTagKind;
use Modules\SAO\Models\Release;
use Modules\SAO\Models\ReleaseTag;

uses(RefreshDatabase::class);

test('a release with no tags has unknown effective maturity', function (): void {
    $release = Release::factory()->observed()->create();

    expect($release->effectiveMaturity())->toBeNull();
});

test('effective maturity is the highest tag kind by precedence', function (): void {
    $release = Release::factory()->create();
    $release->tags()->save(ReleaseTag::factory()->beta()->make());
    $release->tags()->save(ReleaseTag::factory()->alpha()->make());
    $release->tags()->save(ReleaseTag::factory()->make()); // stable (default)

    expect($release->refresh()->effectiveMaturity())->toBe(ReleaseTagKind::Stable);
});

test('a candidate-only release peaks at candidate maturity', function (): void {
    $release = Release::factory()->create();
    $release->tags()->save(ReleaseTag::factory()->alpha()->make());
    $release->tags()->save(ReleaseTag::factory()->candidate()->make());

    expect($release->refresh()->effectiveMaturity())->toBe(ReleaseTagKind::Candidate);
});

test('the curated scope hides observed releases and keeps the rest', function (): void {
    Release::factory()->observed()->create();
    Release::factory()->create();
    Release::factory()->shipped()->create();

    expect(Release::query()->count())->toBe(3);
    expect(Release::query()->curated()->count())->toBe(2);
});
