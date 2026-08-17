<?php

declare(strict_types=1);

use Modules\SAO\Filament\Resources\SourceProfiles\SourceProfileResource;
use Modules\SAO\Models\SourceProfile;

function sao_source_profile_source(string $relativePath): string
{
    return (string) file_get_contents(dirname(__DIR__, 3) . '/app/Filament/Resources/SourceProfiles/' . $relativePath);
}

test('the source profile resource is bound to its model under the SAO group', function (): void {
    expect(SourceProfileResource::getModel())->toBe(SourceProfile::class)
        ->and(SourceProfileResource::getNavigationGroup())->toBe('SAO')
        ->and(SourceProfileResource::getSlug())->toStartWith('sao/');
});

test('the source profile resource exposes the full CRUD page set', function (): void {
    expect(array_keys(SourceProfileResource::getPages()))->toBe(['index', 'create', 'edit']);
});

test('the form authors matchers and field bindings', function (): void {
    $form = sao_source_profile_source('Schemas/SourceProfileForm.php');

    expect($form)->toContain("Repeater::make('matchers')")
        ->and($form)->toContain("TextInput::make('path')")
        ->and($form)->toContain("Select::make('operator')")
        ->and($form)->toContain("KeyValue::make('field_bindings')");
});

test('the table summarises the profile with matcher and binding counts', function (): void {
    $tableSource = sao_source_profile_source('Tables/SourceProfilesTable.php');

    expect($tableSource)->toContain("TextColumn::make('name')")
        ->and($tableSource)->toContain("TextColumn::make('matchers')")
        ->and($tableSource)->toContain("IconColumn::make('is_active')");
});
