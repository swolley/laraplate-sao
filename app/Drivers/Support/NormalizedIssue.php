<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Support;

/**
 * The provider-agnostic shape an `issues` capability returns.
 *
 * A driver maps its remote representation to this; the sync layer translates
 * `remoteStatus`/`remotePriority` through the binding's maps. Statuses and
 * priorities are kept as their remote strings here on purpose — canonicalization
 * is the binding's job, not the driver's.
 */
final readonly class NormalizedIssue
{
    public function __construct(
        public string $remoteId,
        public string $title,
        public ?string $key = null,
        public ?string $body = null,
        public ?string $remoteStatus = null,
        public ?string $remotePriority = null,
        public ?string $assignee = null,
        public ?string $url = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    /**
     * @return array<string, ?string>
     */
    public function toArray(): array
    {
        return [
            'remote_id' => $this->remoteId,
            'key' => $this->key,
            'title' => $this->title,
            'body' => $this->body,
            'remote_status' => $this->remoteStatus,
            'remote_priority' => $this->remotePriority,
            'assignee' => $this->assignee,
            'url' => $this->url,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
