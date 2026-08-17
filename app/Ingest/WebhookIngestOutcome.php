<?php

declare(strict_types=1);

namespace Modules\SAO\Ingest;

/**
 * The transport-level result of one webhook delivery: a short machine label, the
 * HTTP status the endpoint should answer with, and the ids of any signals the
 * delivery produced.
 *
 * A forged or misrouted delivery is a permanent client error (401/422) and is
 * never persisted. An authentic delivery is always accepted (202) — even when it
 * lands nowhere (no binding) or carries no events — so the sender stops retrying
 * and the reason survives as an auditable {@see \Modules\SAO\Models\IngestEvent}.
 */
final readonly class WebhookIngestOutcome
{
    /**
     * @param  list<int>  $signalIds
     */
    private function __construct(
        public string $result,
        public int $httpStatus,
        public array $signalIds = [],
    ) {}

    public static function unsupported(string $reason): self
    {
        return new self($reason, 422);
    }

    public static function unauthorized(): self
    {
        return new self('signature-verification-failed', 401);
    }

    /**
     * @param  list<int>  $signalIds
     */
    public static function accepted(string $result, array $signalIds = []): self
    {
        return new self($result, 202, $signalIds);
    }
}
