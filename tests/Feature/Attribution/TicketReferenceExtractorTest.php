<?php

declare(strict_types=1);

use Modules\SAO\Attribution\TicketReferenceExtractor;
use Modules\SAO\Enums\ChangeRefRelation;

beforeEach(function (): void {
    $this->extractor = new TicketReferenceExtractor();
});

/**
 * @return array<string, ChangeRefRelation>
 */
function relationsByKey(array $references): array
{
    $map = [];

    foreach ($references as $reference) {
        $map[$reference->key] = $reference->relation;
    }

    return $map;
}

test('a closing verb before a key marks it as a fix', function (string $text): void {
    $map = relationsByKey($this->extractor->extract($text));

    expect($map)->toBe(['SAO-1' => ChangeRefRelation::Fixes]);
})->with([
    'fixes' => ['fixes SAO-1'],
    'closes' => ['Closes SAO-1'],
    'resolved with colon' => ['resolved: SAO-1'],
    'leading hash' => ['Fixed #SAO-1'],
    'uppercase verb' => ['FIXES SAO-1'],
]);

test('a bare reference is a mention', function (): void {
    $map = relationsByKey($this->extractor->extract('see SAO-1 for context'));

    expect($map)->toBe(['SAO-1' => ChangeRefRelation::Mentions]);
});

test('only the key the verb precedes is a fix; the rest are mentions', function (): void {
    $map = relationsByKey($this->extractor->extract('Closes SAO-2 and touches SAO-3'));

    expect($map)->toBe([
        'SAO-2' => ChangeRefRelation::Fixes,
        'SAO-3' => ChangeRefRelation::Mentions,
    ]);
});

test('a fix wins when the same key is also mentioned', function (): void {
    $map = relationsByKey($this->extractor->extract('Fix SAO-1. Follow-up on SAO-1 later.'));

    expect($map)->toBe(['SAO-1' => ChangeRefRelation::Fixes]);
});

test('multiple projects and numbers are all captured', function (): void {
    $map = relationsByKey($this->extractor->extract('fixes SAO-10, closes ERP-200, refs CMS-3'));

    expect($map)->toBe([
        'SAO-10' => ChangeRefRelation::Fixes,
        'ERP-200' => ChangeRefRelation::Fixes,
        'CMS-3' => ChangeRefRelation::Mentions,
    ]);
});

test('non-keys and empty text yield nothing', function (): void {
    expect($this->extractor->extract(''))->toBe([])
        ->and($this->extractor->extract('a plain message with no ticket'))->toBe([])
        ->and($this->extractor->extract('order 12-34 shipped'))->toBe([]);
});

test('the closing-verb grammar is configurable', function (): void {
    config(['sao.attribution.closing_verbs' => ['implements']]);

    $map = relationsByKey($this->extractor->extract('implements SAO-1, fixes SAO-2'));

    expect($map)->toBe([
        'SAO-1' => ChangeRefRelation::Fixes,
        'SAO-2' => ChangeRefRelation::Mentions,
    ]);
});
