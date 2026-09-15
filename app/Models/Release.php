<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\ReleaseFactory;
use Modules\SAO\Enums\ReleaseStatus;
use Modules\SAO\Enums\SAOTables;
use Override;

/**
 * A product version of a project, named as its stable label. It gathers the
 * concrete VCS tags that realize it and the tickets attributed to it, so SAO
 * can answer "which release carries this fix" from data rather than guesses.
 *
 * @mixin \Eloquent
 *
 * @property int $id
 * @property int $project_id
 * @property string $version
 * @property ReleaseStatus $status
 * @property \Illuminate\Support\Carbon|null $released_at
 *
 * @mixin IdeHelperRelease
 */
final class Release extends Model
{
    /**
     * @var array<string, mixed>
     */
    #[Override]
    protected $attributes = [
        'status' => ReleaseStatus::Announced->value,
    ];

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'project_id',
        'version',
        'status',
        'released_at',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::Releases->value;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<ReleaseTag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(ReleaseTag::class);
    }

    /**
     * @return HasMany<TicketRelease, $this>
     */
    public function ticketReleases(): HasMany
    {
        return $this->hasMany(TicketRelease::class);
    }

    /**
     * @return Factory<Release>
     */
    protected static function newFactory(): Factory
    {
        return ReleaseFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'status' => ReleaseStatus::class,
            'released_at' => 'datetime',
        ];
    }
}
