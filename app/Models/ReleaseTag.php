<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\ReleaseTagFactory;
use Modules\SAO\Enums\ReleaseTagKind;
use Modules\SAO\Enums\SAOTables;
use Override;

/**
 * A concrete VCS tag realizing a {@see Release}. A `stable` tag makes the
 * release shippable; a `candidate` keeps a testable reference for staging.
 *
 * @mixin \Eloquent
 *
 * @property int $id
 * @property int $release_id
 * @property string $tag
 * @property ReleaseTagKind $kind
 *
 * @mixin IdeHelperReleaseTag
 */
final class ReleaseTag extends Model
{
    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'release_id',
        'tag',
        'kind',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::ReleaseTags->value;

    /**
     * @return BelongsTo<Release, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * @return Factory<ReleaseTag>
     */
    protected static function newFactory(): Factory
    {
        return ReleaseTagFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'release_id' => 'integer',
            'kind' => ReleaseTagKind::class,
        ];
    }
}
