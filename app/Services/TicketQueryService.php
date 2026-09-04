<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Casts\ActionEnum;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Support\PermissionName;
use Modules\SAO\Models\Ticket;

/**
 * The only sanctioned way to read tickets.
 *
 * Core's ACL chain is not automatic at the Eloquent level — HasACL's global
 * scope is an unimplemented TODO — so a service that queries tickets with raw
 * Eloquent silently bypasses row-level visibility. Every read path goes through
 * here or through Core's CRUD layer.
 *
 * Both anchor their ACLs on `select`, the verb Core's CRUD engine reads with
 * ({@see \Modules\Core\Services\Crud\CrudService} uses it for lists, details,
 * history and trees alike). A second read verb of SAO's own would mean two ACL
 * sets on one table, and configuring only one of them would leave the other
 * surface unfiltered.
 */
final readonly class TicketQueryService
{
    public function __construct(private AuthorizationService $authorization) {}

    /**
     * @return Builder<Ticket>
     */
    public function visible(): Builder
    {
        $query = Ticket::query();

        $this->authorization->applyAclFiltersToQuery(
            $query,
            PermissionName::forClass(Ticket::class, ActionEnum::Select->value),
        );

        return $query;
    }
}
