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
        $label = $this->plainText((string) $ticket->title);

        if ($label === '') {
            return null;
        }

        $key = (string) $ticket->key;
        $description = $this->plainText((string) ($ticket->description ?? ''));
        $source = $description === '' ? $label : $description;
        $excerpt = Str::limit($source, 1000, '');
        $truncated = mb_strlen($source) > mb_strlen($excerpt);

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

    /**
     * Reduce a free-text ticket field to plain text safe for evidence:
     * strip markup and control characters, collapse whitespace.
     */
    private function plainText(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            return '';
        }

        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return mb_trim($value);
    }
}
