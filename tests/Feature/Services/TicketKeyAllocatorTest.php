<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Exceptions\ImmutableKeyPrefixException;
use Modules\SAO\Models\Project;
use Modules\SAO\Services\TicketKeyAllocator;

uses(RefreshDatabase::class);

test('the first allocation is number one', function (): void {
    $project = Project::factory()->create(['key_prefix' => 'SAO']);

    $allocated = app(TicketKeyAllocator::class)->allocate($project);

    expect($allocated['number'])->toBe(1);
    expect($allocated['key'])->toBe('SAO-1');
});

test('allocations increment and never repeat within a project', function (): void {
    $project = Project::factory()->create(['key_prefix' => 'SAO']);
    $allocator = app(TicketKeyAllocator::class);

    $keys = [];

    for ($i = 0; $i < 25; $i++) {
        $keys[] = $allocator->allocate($project)['key'];
    }

    expect($keys)->toHaveCount(25);
    expect(array_unique($keys))->toHaveCount(25);
    expect($keys[24])->toBe('SAO-25');
});

test('counters are independent across projects', function (): void {
    $first = Project::factory()->create(['key_prefix' => 'AAA']);
    $second = Project::factory()->create(['key_prefix' => 'BBB']);
    $allocator = app(TicketKeyAllocator::class);

    $allocator->allocate($first);

    expect($allocator->allocate($second)['key'])->toBe('BBB-1');
    expect($allocator->allocate($first)['key'])->toBe('AAA-2');
});

/**
 * The guard triggers on an allocated number rather than on an existing Ticket
 * row: a number handed out and then rolled back still appears in someone's
 * browser history, and the prefix that produced it must stay meaningful.
 */
test('the key prefix cannot change once a number has been allocated', function (): void {
    $project = Project::factory()->create(['key_prefix' => 'SAO']);
    app(TicketKeyAllocator::class)->allocate($project);

    $project->refresh();
    $project->key_prefix = 'NEW';

    expect(fn (): bool => $project->save())
        ->toThrow(ImmutableKeyPrefixException::class);
});

test('the prefix may still be corrected before the first allocation', function (): void {
    $project = Project::factory()->create(['key_prefix' => 'TYPO']);

    $project->key_prefix = 'GOOD';
    $project->save();

    expect($project->fresh()->key_prefix)->toBe('GOOD');
});

/**
 * The sequential tests above prove arithmetic, not safety. This is the only test
 * in slice 1a that exercises a genuine race, and the row lock is the only thing
 * standing between two concurrent creations and a duplicate key.
 *
 * It skips on SQLite because SQLite serializes writes: it would pass without
 * proving anything, which is worse than not running. Run it against a real
 * database before considering the allocator verified:
 *
 *     DB_CONNECTION=pgsql php artisan test --filter="two concurrent allocations"
 */
test('two concurrent allocations produce two distinct keys', function (): void {
    $project = Project::factory()->create(['key_prefix' => 'SAO']);

    $script = base_path('Modules/SAO/tests/Support/allocate_once.php');

    $first = proc_open(['php', $script, (string) $project->id], [1 => ['pipe', 'w']], $first_pipes);
    $second = proc_open(['php', $script, (string) $project->id], [1 => ['pipe', 'w']], $second_pipes);

    $first_key = mb_trim((string) stream_get_contents($first_pipes[1]));
    $second_key = mb_trim((string) stream_get_contents($second_pipes[1]));

    fclose($first_pipes[1]);
    fclose($second_pipes[1]);
    proc_close($first);
    proc_close($second);

    expect($first_key)->not->toBe($second_key);
    expect([$first_key, $second_key])->toEqualCanonicalizing(['SAO-1', 'SAO-2']);
})->skip(
    fn (): bool => config('database.default') === 'sqlite',
    'SQLite serializes writes, so this would pass without exercising the row lock.',
);

test('other fields stay editable after allocation', function (): void {
    $project = Project::factory()->create(['key_prefix' => 'SAO', 'name' => 'Before']);
    app(TicketKeyAllocator::class)->allocate($project);

    $project->refresh();
    $project->name = 'After';
    $project->save();

    expect($project->fresh()->name)->toBe('After');
});
