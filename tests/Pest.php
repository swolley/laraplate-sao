<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

use Modules\SAO\Tests\TestCase;

pest()->extend(TestCase::class)
    ->in(__DIR__ . '/Integration', __DIR__ . '/Feature');
