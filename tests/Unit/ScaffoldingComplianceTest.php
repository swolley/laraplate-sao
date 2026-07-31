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
