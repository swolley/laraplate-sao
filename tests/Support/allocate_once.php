<?php

declare(strict_types=1);

/**
 * Allocates one ticket key for the given project id and prints it.
 *
 * Run as a separate process so two allocations genuinely race, which is the only
 * way to exercise the row lock in TicketKeyAllocator. Used by
 * TicketKeyAllocatorTest and skipped on SQLite, which serializes writes.
 */

require __DIR__ . '/../../../../vendor/autoload.php';

/** @var Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$project = Modules\SAO\Models\Project::query()->findOrFail((int) $argv[1]);

echo $app->make(Modules\SAO\Services\TicketKeyAllocator::class)->allocate($project)['key'];
