<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\ProjectFactory;
use Modules\SAO\Enums\SAOTables;
use Override;

/**
 * @mixin \Eloquent
 *
 * @property int $id
 * @property string $name
 * @property string $key_prefix
 * @property string|null $description
 * @property int $next_ticket_number
 * @property bool $is_active
 */
final class Project extends Model
{
    /**
     * The database carries the same default, but a freshly created model would
     * otherwise report null until refreshed — and the allocator reads this
     * value before incrementing it.
     *
     * @var array<string, mixed>
     */
    #[Override]
    protected $attributes = [
        'next_ticket_number' => 0,
    ];

    /**
     * `next_ticket_number` is deliberately absent: only TicketKeyAllocator may
     * move it, and only under a row lock.
     *
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'name',
        'key_prefix',
        'description',
        'is_active',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::Projects->value;

    /**
     * @return array<string, array<string, list<string>>>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();

        $table = SAOTables::Projects->value;

        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255'],
            'key_prefix' => ['required', 'string', 'regex:/^[A-Z][A-Z0-9]{1,9}$/', "unique:{$table},key_prefix"],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $rules;
    }

    /**
     * @return Factory<Project>
     */
    protected static function newFactory(): Factory
    {
        return ProjectFactory::new();
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'next_ticket_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
