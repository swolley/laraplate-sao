<?php

declare(strict_types=1);

namespace Modules\SAO\ApplicationContent;

use Illuminate\Support\Str;
use Modules\Core\ApplicationContent\Data\ApplicationContentHit;
use Modules\SAO\Models\Ticket;

final class SaoTicketEvidenceProjector
{
    public function project(Ticket $ticket, string $requestedLocale, string $strategy, ?float $score): ?ApplicationContentHit
    {
        $label = mb_trim((string) $ticket->title);

        if ($label === '') {
            return null;
        }

        $key = (string) $ticket->key;
        $description = (string) ($ticket->description ?? '');
        $excerpt = Str::limit($description, 1000, '');
        $truncated = mb_strlen($description) > mb_strlen($excerpt);

        return new ApplicationContentHit(
            id: 'sao.tickets:' . $key,
            source: 'sao.tickets',
            module: 'sao',
            entity: 'tickets',
            recordKey: $key,
            excerpt: $excerpt,
            label: Str::limit($label, 200, ''),
            canonicalReference: '/app/sao/tickets/' . $key,
            locale: $requestedLocale,
            strategy: $strategy,
            score: $score,
            revision: $ticket->updated_at?->toIso8601String(),
            truncated: $truncated,
        );
    }
}
