<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Modules\SAO\Enums\SyncDirection;
use Modules\SAO\Models\ProjectBinding;

/**
 * Performs the cutover of a binding after a migration: SAO becomes authoritative
 * for its tickets. It flips the binding's `sync_direction` to the target —
 * `disabled` (fully switched to Laraplate, the external tracker abandoned) by
 * default, or `outbound` to keep pushing SAO's changes back during a transition.
 *
 * The `TicketLink`s are kept as provenance — cutover changes who owns the tickets,
 * not the record of where they came from.
 */
final readonly class BindingCutoverService
{
    public function cutover(ProjectBinding $binding, SyncDirection $target = SyncDirection::Disabled): ProjectBinding
    {
        $binding->forceFill(['sync_direction' => $target])->save();

        return $binding;
    }
}
