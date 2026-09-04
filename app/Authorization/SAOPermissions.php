<?php

declare(strict_types=1);

namespace Modules\SAO\Authorization;

use Modules\Core\Authorization\Contracts\DeclaresPermissions;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\IngestEvent;
use Modules\SAO\Models\OwnershipSuggestion;
use Modules\SAO\Models\Ticket;
use Override;

/**
 * SAO domain permissions.
 *
 * Only what the domain adds on top of CRUD: assigning a ticket, moving it
 * through its workflow, overriding a workflow that would otherwise deadlock the
 * work, closing a ticket by applying a closure policy, accepting an ownership
 * suggestion, probing a connection's health, and replaying a stored ingest
 * event. The last four back the SPA-facing domain actions in
 * {@see \Modules\SAO\Services\DomainActions\SaoDomainActionRegistrar}.
 *
 * The generic verbs are deliberately absent. `permission:refresh` already
 * generates `select`, `insert`, `update`, `delete` and the rest for every table
 * it manages, so declaring them here would only duplicate them, and a declared
 * name is exempt from the command's own cleanup branches — which would let this
 * file override a Core decision without saying so.
 */
final class SAOPermissions implements DeclaresPermissions
{
    #[Override]
    public static function operations(): array
    {
        return [
            Ticket::class => ['assign', 'transition', 'transition_override', 'close'],
            OwnershipSuggestion::class => ['accept'],
            Connection::class => ['health'],
            IngestEvent::class => ['replay'],
        ];
    }

    #[Override]
    public static function excludedModels(): array
    {
        return [];
    }
}
