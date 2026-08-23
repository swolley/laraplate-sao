<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Support;

use Carbon\CarbonInterface;
use Modules\SAO\Enums\DeploymentStatus;

/**
 * The provider-agnostic shape a `deploy` capability returns and the deploy
 * ingest consumes. A driver maps its remote representation to this; SAO resolves
 * the environment and release from the names/version here. `externalId` together
 * with the connection is the idempotency key — a re-delivery carrying the same
 * one updates the existing deployment instead of creating a second.
 */
final readonly class DeployEvent
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $version,
        public DeploymentStatus $status,
        public ?string $environmentName = null,
        public ?string $externalId = null,
        public ?CarbonInterface $startedAt = null,
        public ?CarbonInterface $finishedAt = null,
        public array $meta = [],
    ) {}
}
