<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Illuminate\Support\Collection;
use Modules\Core\Enums\VersionChangeType;
use Modules\Core\Models\Version;
use Modules\SAO\Data\TimelineEntry;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketComment;

/**
 * Merges comments and Core versions into one ordered stream.
 *
 * This is the single place that knows where history comes from. If querying
 * versions proves slow, a dedicated activity table replaces the second half of
 * this method and nothing else in the module changes.
 */
final class TicketTimelineService
{
    /**
     * @return Collection<int, TimelineEntry>
     */
    public function for(Ticket $ticket): Collection
    {
        return $this->comments($ticket)
            ->concat($this->changes($ticket))
            ->sortBy(fn (TimelineEntry $entry): int => $entry->occurredAt()->getTimestamp())
            ->values();
    }

    /**
     * @return Collection<int, TimelineEntry>
     */
    private function changes(Ticket $ticket): Collection
    {
        $user_key = config('versionable.user_foreign_key', 'user_id');

        return Version::query()
            ->where('versionable_type', $ticket->getMorphClass())
            ->where('versionable_id', $ticket->getKey())
            ->orderBy('created_at')
            ->get()
            ->map(function (Version $version) use ($user_key): TimelineEntry {
                /** @var array<string, mixed> $contents */
                $contents = $version->getAttribute('contents') ?? [];

                /** @var array<string, mixed> $original */
                $original = $version->getAttribute('original_contents') ?? [];

                $user_id = $version->getAttribute($user_key);
                $author_id = $user_id === null ? null : (int) $user_id;
                $occurred_at = $version->getAttribute('created_at');

                // The opening of a ticket is history too, and the first line any
                // tracker shows — but it is not a field change, so it gets its
                // own kind rather than being hidden or mislabelled.
                return $version->getAttribute('change_type') === VersionChangeType::Created
                    ? TimelineEntry::created($occurred_at, $author_id, $contents)
                    : TimelineEntry::change($occurred_at, $author_id, $contents, $original);
            })
            ->filter(fn (TimelineEntry $entry): bool => $entry->changes() !== [])
            ->values();
    }

    /**
     * @return Collection<int, TimelineEntry>
     */
    private function comments(Ticket $ticket): Collection
    {
        return $ticket->comments()
            ->orderBy('created_at')
            ->get()
            ->map(fn (TicketComment $comment): TimelineEntry => TimelineEntry::comment(
                $comment->created_at,
                $comment->author_id,
                $comment->source_key,
                $comment->body,
            ))
            ->values();
    }
}
