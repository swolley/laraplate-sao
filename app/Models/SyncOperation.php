<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Enums\SyncOutcome;
use Override;

/**
 * The idempotency ledger: one row per completed synchronization, keyed so a
 * retry with the same binding, ticket and content is recognised and skipped
 * rather than producing a second remote write.
 *
 * @property int $binding_id
 * @property string $idempotency_key
 * @property SyncOutcome $outcome
 *
 * @mixin \Eloquent
 */
final class SyncOperation extends Model
{
    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'binding_id',
        'idempotency_key',
        'outcome',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::SyncOperations->value;

    /**
     * @return BelongsTo<ProjectBinding, $this>
     */
    public function binding(): BelongsTo
    {
        return $this->belongsTo(ProjectBinding::class, 'binding_id');
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();

        $rules['create'] = array_merge($rules['create'], [
            'binding_id' => ['required', 'integer'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'outcome' => ['required', 'string', 'in:' . implode(',', SyncOutcome::values())],
        ]);

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'outcome' => SyncOutcome::class,
        ];
    }
}
