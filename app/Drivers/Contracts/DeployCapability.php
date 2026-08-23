<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Contracts;

use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\DeployEvent;

/**
 * Receive deploy/rollout notifications over a push transport: verify the
 * delivery's signature, then unpack it into normalized {@see DeployEvent}s the
 * deploy ingest records.
 *
 * The counterpart to {@see LogsCapability} for deployments. A driver verifies the
 * delivery with its own signature/token scheme (so a forged body never reaches
 * the store) and maps the source's payload to the provider-agnostic shape; SAO
 * resolves the environment/release and records the {@see \Modules\SAO\Models\Deployment}.
 * Never a promote/rollback gate — SAO only records what happened.
 */
interface DeployCapability
{
    /**
     * @param  array<string, string>  $headers
     */
    public function verifySignature(BindingContext $context, string $payload, array $headers): bool;

    /**
     * @return list<DeployEvent>
     */
    public function unpack(BindingContext $context, string $payload): array;
}
