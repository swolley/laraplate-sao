<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\SourceProfileFactory;
use Modules\SAO\Enums\SAOTables;
use Override;

/**
 * A stored normalization profile: the matchers that decide which payloads it
 * applies to, and the field bindings (canonical field => payload dot-path) that
 * turn a payload into canonical fields. Supporting a new source is adding a row,
 * not writing code.
 *
 * @mixin \Eloquent
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property list<array{path: string, operator: string, value?: mixed}> $matchers
 * @property array<string, string> $field_bindings
 *
 * @mixin IdeHelperSourceProfile
 */
final class SourceProfile extends Model
{
    /**
     * @var array<string, mixed>
     */
    #[Override]
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'name',
        'is_active',
        'matchers',
        'field_bindings',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::SourceProfiles->value;

    /**
     * @return array<string, array<string, list<string>>>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();

        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'matchers' => ['required', 'array'],
            'field_bindings' => ['required', 'array'],
        ]);

        return $rules;
    }

    /**
     * @return Factory<SourceProfile>
     */
    protected static function newFactory(): Factory
    {
        return SourceProfileFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'matchers' => 'array',
            'field_bindings' => 'array',
        ];
    }
}
