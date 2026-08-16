<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Helpers\HasMedia;
use Modules\Core\Locking\Traits\HasOptimisticLocking;
use Modules\Core\Models\User;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\TicketFactory;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Enums\TicketPriority;
use Modules\SAO\Enums\TicketRelationType;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;
use Spatie\MediaLibrary\HasMedia as MediaContract;

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
 * @property \Illuminate\Support\Carbon|null $due_at
 *
 * @mixin IdeHelperTicket
 */
final class Ticket extends Model implements MediaContract
{
    use HasMedia;
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
        'due_at',
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
            'due_at' => ['nullable', 'date'],
        ]);

        $rules['update'] = array_merge($rules['update'], [
            'priority' => ['sometimes', 'string', TicketPriority::validationRule()],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer'],
            'due_at' => ['nullable', 'date'],
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
     * @return HasMany<ChangeRef, $this>
     */
    public function changeRefs(): HasMany
    {
        return $this->hasMany(ChangeRef::class);
    }

    /**
     * @return HasMany<TicketComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * @return BelongsToMany<Label, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, SAOTables::TicketLabel->value);
    }

    /**
     * @return HasMany<TicketLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(TicketLink::class);
    }

    /**
     * Outgoing typed relations (this ticket is the source).
     *
     * @return HasMany<TicketRelation, $this>
     */
    public function relations(): HasMany
    {
        return $this->hasMany(TicketRelation::class, 'source_ticket_id');
    }

    /**
     * Tickets on the far side of an outgoing relation of the given type. A
     * symmetric type (`relates`) also resolves relations pointing at this ticket.
     *
     * @return EloquentCollection<int, Ticket>
     */
    public function relatedVia(TicketRelationType $type): EloquentCollection
    {
        $ids = TicketRelation::query()
            ->where('source_ticket_id', $this->getKey())
            ->where('type', $type->value)
            ->pluck('target_ticket_id');

        if (! $type->isDirectional()) {
            $ids = $ids->merge(
                TicketRelation::query()
                    ->where('target_ticket_id', $this->getKey())
                    ->where('type', $type->value)
                    ->pluck('source_ticket_id'),
            );
        }

        return self::query()->whereIn('id', $ids->unique()->all())->get();
    }

    /**
     * Tickets holding an incoming relation of the given type — the inverse
     * reading (e.g. the inverse of `blocks` is "blocked by").
     *
     * @return EloquentCollection<int, Ticket>
     */
    public function inverselyRelatedVia(TicketRelationType $type): EloquentCollection
    {
        $ids = TicketRelation::query()
            ->where('target_ticket_id', $this->getKey())
            ->where('type', $type->value)
            ->pluck('source_ticket_id');

        return self::query()->whereIn('id', $ids->all())->get();
    }

    /**
     * A ticket with no external link is internal; SAO is authoritative for it.
     */
    public function isInternal(): bool
    {
        return $this->links()->doesntExist();
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Files attached to the ticket live in the Core-owned media library.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
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
     * Users following the ticket. Notification delivery is out of scope here;
     * this only records who watches.
     *
     * @return BelongsToMany<User, $this>
     */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, SAOTables::TicketWatchers->value, 'ticket_id', 'user_id');
    }

    /**
     * Idempotently record the user as a watcher.
     */
    public function watch(User $user): void
    {
        $this->watchers()->syncWithoutDetaching([$user->getKey()]);
    }

    /**
     * Remove the user from the watchers.
     */
    public function unwatch(User $user): void
    {
        $this->watchers()->detach($user->getKey());
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
     * Past-due tickets that still need work. A ticket in a terminal status
     * (closed or rejected) is never overdue no matter its due date.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function overdue(Builder $query): Builder
    {
        return $query
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereHas('status', static function (Builder $status): void {
                $status->whereNotIn('category', self::terminalStatusCategories());
            });
    }

    /**
     * Tickets whose due date falls within the next `$days` (or interval) from now.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function dueWithin(Builder $query, CarbonInterval|int $days): Builder
    {
        $interval = is_int($days) ? CarbonInterval::days($days) : $days;

        return $query
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), now()->add($interval)]);
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
            'due_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    private static function terminalStatusCategories(): array
    {
        return array_values(array_map(
            static fn (StatusCategory $category): string => $category->value,
            array_filter(
                StatusCategory::cases(),
                static fn (StatusCategory $category): bool => $category->isTerminal(),
            ),
        ));
    }
}
