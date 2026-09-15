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
 *
 * @mixin \Eloquent
 *
 * @property int $id
 * @property int $signal_id
 * @property string|null $environment
 * @property array<string, mixed>|null $context
 * @property \Illuminate\Support\Carbon|null $occurred_at
 *
 * @mixin IdeHelperSignalOccurrence
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
            'occurred_at' => 'datetime',
        ];
    }
}
