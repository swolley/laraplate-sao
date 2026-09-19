<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\SignalOccurrenceFactory;
use Modules\SAO\Enums\SAOTables;
use Override;

/**
 * One individual occurrence of a {@see Signal}, carrying the environment it came
 * from and optional payload context. Kept with configurable retention — needed
 * for "recurring for three days" and closure evidence, not forever.
 */
final class SignalOccurrence extends Model
{
    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'signal_id',
        'environment',
        'context',
        'affected_version',
        'affected_release_id',
        'occurred_at',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::SignalOccurrences->value;

    /**
     * @return BelongsTo<Signal, $this>
     */
    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }

    /**
     * The censused release for the detected version, when the version was
     * normalizable and promoted to the census. Null keeps the raw string on
     * {@see self::$affected_version} as an audit-only record.
     *
     * @return BelongsTo<Release, $this>
     */
    public function affectedRelease(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'affected_release_id');
    }

    /**
     * @return Factory<SignalOccurrence>
     */
    protected static function newFactory(): Factory
    {
        return SignalOccurrenceFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'signal_id' => 'integer',
            'context' => 'array',
            'affected_release_id' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
