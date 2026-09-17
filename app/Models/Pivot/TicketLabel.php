<?php

declare(strict_types=1);

namespace Modules\SAO\Models\Pivot;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Pivot;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Models\Label;
use Modules\SAO\Models\Ticket;
use Override;

/**
 * The assignment of a label to a ticket. Modelled explicitly so the moment a
 * label was attached (or last touched) is recorded and observable, rather than
 * living in an anonymous pivot row.
 *
 * @mixin IdeHelperTicketLabel
 */
final class TicketLabel extends Pivot
{
    #[Override]
    public $incrementing = true;

    #[Override]
    public $timestamps = true;

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::TicketLabel->value;

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'label_id' => 'integer',
        ];
    }
}
