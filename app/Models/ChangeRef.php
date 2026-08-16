<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\ChangeRefFactory;
use Modules\SAO\Enums\ChangeRefType;
use Modules\SAO\Enums\SAOTables;
use Override;

/**
 * The link between a code artefact — a commit, pull request or tag — and a
 * ticket, recording the source that produced it. It is the raw material of
 * code-to-work correlation (commit → ticket) built in phase 6.
 *
 * @mixin \Eloquent
 *
 * @property int $id
 * @property int $ticket_id
 * @property ChangeRefType $type
 * @property string $identifier
 * @property string|null $url
 * @property string|null $source
 * @property \Illuminate\Support\Carbon|null $merged_at
 *
 * @mixin IdeHelperChangeRef
 */
final class ChangeRef extends Model
{
    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'ticket_id',
        'type',
        'identifier',
        'url',
        'source',
        'merged_at',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::ChangeRefs->value;

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Whether this reference is a merged pull request.
     */
    public function isMergedPullRequest(): bool
    {
        return $this->type === ChangeRefType::PullRequest && $this->merged_at !== null;
    }

    /**
     * Merged pull-request references only.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function mergedPullRequests(Builder $query): Builder
    {
        return $query
            ->where('type', ChangeRefType::PullRequest->value)
            ->whereNotNull('merged_at');
    }

    /**
     * @return Factory<ChangeRef>
     */
    protected static function newFactory(): Factory
    {
        return ChangeRefFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'type' => ChangeRefType::class,
            'merged_at' => 'datetime',
        ];
    }
}
