<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Locking\Traits\HasOptimisticLocking;
use Modules\Core\Models\User;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\TicketFactory;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Enums\TicketPriority;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * @mixin \Eloquent
 *
 * @property int $id
 * @property int $project_id
 * @property int $number
 * @property string $key
 * @property int $ticket_type_id
 * @property int $ticket_status_id
 * @property TicketPriority $priority
 * @property string $title
 * @property string|null $description
 * @property int|null $reporter_id
 * @property int|null $assignee_id
 */
final class Ticket extends Model
{
    use HasOptimisticLocking;

    /**
     * Mirrors the migration default so a new instance reports what it will hold
     * once persisted.
     *
     * @var array<string, mixed>
     */
    #[Override]
    protected $attributes = [
        'priority' => TicketPriority::Normal->value,
    ];

    /**
     * `ticket_status_id` is absent on purpose: WorkflowService is the only path
     * to a status change, so mass assignment must not offer a shortcut. `number`
     * and `key` come from TicketKeyAllocator, never from a request.
     *
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'project_id',
        'ticket_type_id',
        'priority',
        'title',
        'description',
        'reporter_id',
        'assignee_id',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::Tickets->value;

    /**
     * Which attributes the history records. Restricting the list keeps the
     * timeline about the ticket rather than about its bookkeeping.
     *
     * @var list<string>
     */
    protected $versionable = [
        'title',
        'description',
        'priority',
        'ticket_status_id',
        'ticket_type_id',
        'assignee_id',
    ];

    /**
     * Versioning is off until a model declares a strategy — Core resolves it
     * from a per-model setting otherwise, which would make the ticket history
     * depend on configuration that may not exist. DIFF records only what
     * changed, which is what a timeline needs.
     */
    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    /**
     * @return array<string, array<string, list<string>>>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();

        $rules['create'] = array_merge($rules['create'], [
            'project_id' => ['required', 'integer', 'exists:' . SAOTables::Projects->value . ',id'],
            'ticket_type_id' => ['required', 'integer', 'exists:' . SAOTables::TicketTypes->value . ',id'],
            'priority' => ['sometimes', 'string', TicketPriority::validationRule()],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer'],
        ]);

        $rules['update'] = array_merge($rules['update'], [
            'priority' => ['sometimes', 'string', TicketPriority::validationRule()],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer'],
        ]);

        return $rules;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return HasMany<TicketComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return BelongsTo<TicketStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'ticket_status_id');
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    /**
     * @return Factory<Ticket>
     */
    protected static function newFactory(): Factory
    {
        return TicketFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'number' => 'integer',
            'ticket_type_id' => 'integer',
            'ticket_status_id' => 'integer',
            'priority' => TicketPriority::class,
        ];
    }
}
