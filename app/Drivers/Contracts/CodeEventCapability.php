<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Contracts;

use Modules\SAO\Attribution\CodeReference;
use Modules\SAO\Drivers\Support\BindingContext;

/**
 * Receive code events (a merged pull request, a pushed commit) over a push
 * transport: verify the delivery's signature, then unpack it into normalized
 * {@see CodeReference}s the attribution writer records.
 *
 * The push counterpart to the pull commit scan for code-to-work attribution. A
 * driver verifies the delivery with its own signature/token scheme and maps the
 * source payload to the transport-agnostic {@see CodeReference}; SAO extracts the
 * ticket references and links them. Never a rollout gate.
 */
interface CodeEventCapability
{
    /**
     * @param  array<string, string>  $headers
     */
    public function verifySignature(BindingContext $context, string $payload, array $headers): bool;

    /**
     * @return list<CodeReference>
     */
    public function unpack(BindingContext $context, string $payload): array;
}
