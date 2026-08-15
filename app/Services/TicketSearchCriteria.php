<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\SAO\Enums\TicketPriority;

/**
 * An immutable, JSON-serialisable description of a ticket search. It carries no
 * behaviour of its own; {@see TicketSearchService} turns it into a query and
 * {@see \Modules\SAO\Models\SavedFilter} persists its array form.
 */
final readonly class TicketSearchCriteria
{
    public function __construct(
        public ?string $text = null,
        public ?int $statusId = null,
        public ?int $typeId = null,
        public ?TicketPriority $priority = null,
        public ?int $assigneeId = null,
        public ?int $labelId = null,
        public ?CarbonInterface $dueBefore = null,
        public ?CarbonInterface $dueAfter = null,
        public bool $isOverdue = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            text: isset($data['text']) ? (string) $data['text'] : null,
            statusId: isset($data['status_id']) ? (int) $data['status_id'] : null,
            typeId: isset($data['type_id']) ? (int) $data['type_id'] : null,
            priority: isset($data['priority']) ? TicketPriority::from((string) $data['priority']) : null,
            assigneeId: isset($data['assignee_id']) ? (int) $data['assignee_id'] : null,
            labelId: isset($data['label_id']) ? (int) $data['label_id'] : null,
            dueBefore: isset($data['due_before']) ? Carbon::parse((string) $data['due_before']) : null,
            dueAfter: isset($data['due_after']) ? Carbon::parse((string) $data['due_after']) : null,
            isOverdue: (bool) ($data['is_overdue'] ?? false),
        );
    }

    /**
     * Only the set criteria are emitted, so an empty search serialises to `[]`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'text' => $this->text,
            'status_id' => $this->statusId,
            'type_id' => $this->typeId,
            'priority' => $this->priority?->value,
            'assignee_id' => $this->assigneeId,
            'label_id' => $this->labelId,
            'due_before' => $this->dueBefore?->toIso8601String(),
            'due_after' => $this->dueAfter?->toIso8601String(),
            'is_overdue' => $this->isOverdue ?: null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
