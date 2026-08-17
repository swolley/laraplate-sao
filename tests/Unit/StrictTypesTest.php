<?php

declare(strict_types=1);

/**
 * Phase 0 compliance: every PHP file in the module declares strict types,
 * matching the `declare_strict_types` Pint rule.
 */
test('every PHP source file declares strict types', function (): void {
    $module_root = dirname(__DIR__, 2);

    $offenders = [];

    foreach (['app', 'config', 'database', 'routes'] as $directory) {
        $path = $module_root . '/' . $directory;

        if (! is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (! str_contains($contents, 'declare(strict_types=1);')) {
                $offenders[] = str_replace($module_root . '/', '', $file->getPathname());
            }
        }
    }

    expect($offenders)->toBe([]);
});
