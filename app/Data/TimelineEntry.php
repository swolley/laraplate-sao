<?php

declare(strict_types=1);

namespace Modules\SAO\Data;

use Carbon\CarbonInterface;

/**
 * One thing that happened to a ticket: a comment, or a field change.
 *
 * The timeline is a read model. There is no activity table — this merges
 * comments with Core's versions.
 */
final readonly class TimelineEntry
{
    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $previous
     */
    private function __construct(
        private CarbonInterface $occurred_at,
        private string $kind,
        private ?int $author_id,
        private ?string $source_key,
        private ?string $body,
        private array $changes,
        private array $previous,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $previous
     */
    public static function change(
        CarbonInterface $occurred_at,
        ?int $author_id,
        array $changes,
        array $previous = [],
    ): self {
        return new self($occurred_at, 'change', $author_id, null, null, $changes, $previous);
    }

    public static function comment(
        CarbonInterface $occurred_at,
        ?int $author_id,
        ?string $source_key,
        string $body,
    ): self {
        return new self($occurred_at, 'comment', $author_id, $source_key, $body, [], []);
    }

    /**
     * The ticket being opened — history, but not a field change.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function created(CarbonInterface $occurred_at, ?int $author_id, array $attributes): self
    {
        return new self($occurred_at, 'created', $author_id, null, null, $attributes, []);
    }

    public function authorId(): ?int
    {
        return $this->author_id;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    /**
     * The attributes as they are after the change.
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        return $this->changes;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function occurredAt(): CarbonInterface
    {
        return $this->occurred_at;
    }

    /**
     * The same attributes as they were before, so the UI can render "from X to
     * Y" rather than only naming what moved.
     *
     * @return array<string, mixed>
     */
    public function previous(): array
    {
        return $this->previous;
    }

    public function sourceKey(): ?string
    {
        return $this->source_key;
    }
}
