<?php

declare(strict_types=1);

namespace Modules\SAO\Events;

use Modules\SAO\Models\Deployment;

/**
 * A deployment was persisted (created or its status advanced) by the ingest.
 *
 * This is the correlation seam #2 and future automation subscribe to: a listener
 * can, for example, annotate a rolled-back deploy's release tickets, or trigger a
 * release-health re-check on a terminal deploy. The ingest itself never opens
 * tickets — it only records the fact and announces it.
 */
final readonly class DeploymentRecorded
{
    public function __construct(
        public Deployment $deployment,
        public bool $wasRecentlyCreated,
    ) {}
}
