<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\EnvironmentFactory;
use Modules\SAO\Enums\SAOTables;
use Override;

/**
 * A deployment target of a project — production, staging, and so on. It records
 * the version last seen running and when, so the deploy census can answer "what
 * runs where" with an honest freshness rather than an assumed one.
 *
 * @mixin \Eloquent
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $current_version
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 *
 * @mixin IdeHelperEnvironment
 */
final class Environment extends Model
{
    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'project_id',
        'name',
        'current_version',
        'last_seen_at',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::Environments->value;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return Factory<Environment>
     */
    protected static function newFactory(): Factory
    {
        return EnvironmentFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }
}
