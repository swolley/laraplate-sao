<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\WorkflowTransitionFactory;
use Modules\SAO\Enums\SAOTables;
use Override;

/**
 * One permitted move within a scheme. A null `from_status_id` is the creation
 * transition, which is how a scheme declares the status a new ticket starts in.
 *
 * @mixin \Eloquent
 *
 * @property int $id
 * @property int $workflow_scheme_id
 * @property int|null $from_status_id
 * @property int $to_status_id
 * @property string $label
 * @property string|null $required_permission
 *
 * @mixin IdeHelperWorkflowTransition
 */
final class WorkflowTransition extends Model
{
    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'workflow_scheme_id',
        'from_status_id',
        'to_status_id',
        'label',
        'required_permission',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::WorkflowTransitions->value;

    /**
     * @return array<string, array<string, list<string>>>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();

        $statuses = SAOTables::TicketStatuses->value;

        $rules['create'] = array_merge($rules['create'], [
            'workflow_scheme_id' => ['required', 'integer', 'exists:' . SAOTables::WorkflowSchemes->value . ',id'],
            'from_status_id' => ['nullable', 'integer', "exists:{$statuses},id"],
            'to_status_id' => ['required', 'integer', "exists:{$statuses},id"],
            'label' => ['required', 'string', 'max:255'],
            'required_permission' => ['nullable', 'string', 'max:255'],
        ]);

        $rules['update'] = array_merge($rules['update'], [
            'label' => ['sometimes', 'string', 'max:255'],
            'required_permission' => ['nullable', 'string', 'max:255'],
        ]);

        return $rules;
    }

    public function isInitial(): bool
    {
        return $this->from_status_id === null;
    }

    /**
     * @return BelongsTo<WorkflowScheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(WorkflowScheme::class, 'workflow_scheme_id');
    }

    /**
     * @return BelongsTo<TicketStatus, $this>
     */
    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'from_status_id');
    }

    /**
     * @return BelongsTo<TicketStatus, $this>
     */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'to_status_id');
    }

    /**
     * @return Factory<WorkflowTransition>
     */
    protected static function newFactory(): Factory
    {
        return WorkflowTransitionFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'workflow_scheme_id' => 'integer',
            'from_status_id' => 'integer',
            'to_status_id' => 'integer',
        ];
    }
}
