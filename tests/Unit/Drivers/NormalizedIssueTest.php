<?php

declare(strict_types=1);

use Modules\SAO\Drivers\Support\NormalizedIssue;

test('a normalized issue round-trips through toArray', function (): void {
    $issue = new NormalizedIssue(
        remoteId: '42',
        key: 'SAO-42',
        title: 'A bug',
        body: 'It broke',
        remoteStatus: 'Done',
        remotePriority: 'High',
        assignee: 'marco',
        url: 'https://tracker.test/issues/42',
        createdAt: '2026-08-15T10:00:00Z',
        updatedAt: '2026-08-15T11:00:00Z',
    );

    expect($issue->toArray())->toBe([
        'remote_id' => '42',
        'key' => 'SAO-42',
        'title' => 'A bug',
        'body' => 'It broke',
        'remote_status' => 'Done',
        'remote_priority' => 'High',
        'assignee' => 'marco',
        'url' => 'https://tracker.test/issues/42',
        'created_at' => '2026-08-15T10:00:00Z',
        'updated_at' => '2026-08-15T11:00:00Z',
    ]);
});

test('optional fields default to null', function (): void {
    $issue = new NormalizedIssue(remoteId: '1', title: 'x');

    expect($issue->key)->toBeNull()
        ->and($issue->remoteStatus)->toBeNull()
        ->and($issue->toArray()['remote_id'])->toBe('1');
});
