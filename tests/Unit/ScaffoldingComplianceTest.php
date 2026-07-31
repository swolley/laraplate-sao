<?php

declare(strict_types=1);

/**
 * Phase 0 compliance: SAO must ship the same scaffolding as the other
 * Laraplate modules. Later tasks extend this dataset.
 */
it('ships the required scaffolding file', function (string $relative_path): void {
    $absolute_path = dirname(__DIR__, 2) . '/' . $relative_path;

    expect(file_exists($absolute_path))->toBeTrue("Missing required file: {$relative_path}");
})->with([
    'LICENSE',
    'README.md',
    'CHANGELOG.md',
    'cliff.toml',
    '.gitignore',
    'module.json',
    'composer.json',
    'docs/GLOSSARY.md',
    'docs/rag/GLOSSARY.md',
    'docs/rag/MODULE.md',
    'phpunit.xml',
    'phpstan.neon',
    'pint.json',
    'peck.json',
    'rector.php',
]);

test('the licence is the AGPL text used by the sibling modules', function (): void {
    $licence = file_get_contents(dirname(__DIR__, 2) . '/LICENSE');

    expect($licence)->toBeString();
    expect((string) $licence)->toContain('GNU AFFERO GENERAL PUBLIC LICENSE');
    expect((string) $licence)->toContain('Version 3, 19 November 2007');
});

test('the readme names the module and its licence', function (): void {
    $readme = file_get_contents(dirname(__DIR__, 2) . '/README.md');

    expect($readme)->toBeString();
    expect((string) $readme)->toContain('SAO');
    expect((string) $readme)->toContain('Simply Another Orchestrator');
    expect((string) $readme)->toContain('GNU AGPL v3');
});

it('ships the executable release script', function (string $relative_path): void {
    $absolute_path = dirname(__DIR__, 2) . '/' . $relative_path;

    expect(file_exists($absolute_path))->toBeTrue("Missing required file: {$relative_path}");
    expect(is_executable($absolute_path))->toBeTrue("File must be executable: {$relative_path}");
})->with([
    'scripts/version.sh',
    'scripts/setup-hooks.sh',
    'scripts/hooks/post-commit',
]);

test('the module declares its own agent rules', function (): void {
    $rules_path = dirname(__DIR__, 2) . '/.cursor/rules/module-context.mdc';

    expect(file_exists($rules_path))->toBeTrue();

    $contents = file_get_contents($rules_path);

    expect($contents)->toBeString();
    expect((string) $contents)->toContain('Modules/SAO');
    expect((string) $contents)->toContain('Core');
});

test('pint enforces strict types across the module', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2) . '/pint.json');

    expect($contents)->toBeString();

    /** @var array{rules: array<string, mixed>} $config */
    $config = json_decode((string) $contents, true, 512, JSON_THROW_ON_ERROR);

    expect($config['rules']['declare_strict_types'] ?? null)->toBeTrue();
});

test('phpunit declares the three module test suites', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2) . '/phpunit.xml');

    expect($contents)->toBeString();
    expect((string) $contents)->toContain('name="Unit"');
    expect((string) $contents)->toContain('name="Integration"');
    expect((string) $contents)->toContain('name="Feature"');
});

test('the RAG glossary mirrors the human glossary', function (): void {
    $module_root = dirname(__DIR__, 2);

    $human = file_get_contents($module_root . '/docs/GLOSSARY.md');
    $rag = file_get_contents($module_root . '/docs/rag/GLOSSARY.md');

    expect($human)->toBeString();
    expect($rag)->toBeString();

    foreach (['Connection', 'Signal', 'Ticket', 'ChangeRef', 'ClosurePolicy'] as $term) {
        expect((string) $human)->toContain($term);
        expect((string) $rag)->toContain($term);
    }
});
