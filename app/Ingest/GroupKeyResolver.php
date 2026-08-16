<?php

declare(strict_types=1);

namespace Modules\SAO\Ingest;

/**
 * Decides a received error's group key: a source-provided **native key**,
 * namespaced per source (`sentry:issue-42`) so keys from different systems
 * cannot collide, or — only in its absence — a key computed from the payload
 * frame via the shared fingerprint chain.
 */
final readonly class GroupKeyResolver
{
    public function __construct(private PayloadFrameResolver $payloadResolver) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolve(array $payload): string
    {
        $native = $payload['native_key'] ?? null;

        if (is_string($native) && $native !== '') {
            return $this->namespaced($native, $payload['source'] ?? null);
        }

        return $this->payloadResolver->key($payload);
    }

    private function namespaced(string $native, mixed $source): string
    {
        if (str_contains($native, ':') || ! is_string($source) || $source === '') {
            return $native;
        }

        return "{$source}:{$native}";
    }
}
