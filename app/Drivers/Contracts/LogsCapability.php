<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Contracts;

use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\Page;

/**
 * Verify a delivery signature, unpack it into events, and declare whether the
 * source carries a native grouping key.
 *
 * Error trackers (Sentry, GlitchTip) arrive with a stable native key SAO must
 * respect; log/alert systems (Graylog, Loki) deliver raw events for which SAO
 * computes its own key (spec §5). Contract shape only in phase 3a; a real driver
 * implements it from phase 2/4.
 */
interface LogsCapability
{
    /**
     * @param  array<string, string>  $headers
     */
    public function verifySignature(BindingContext $context, string $payload, array $headers): bool;

    public function unpack(BindingContext $context, string $payload): Page;

    public function carriesNativeGroupKey(): bool;
}
