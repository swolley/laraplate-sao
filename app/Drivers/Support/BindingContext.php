<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Support;

/**
 * The resolved view a capability call operates on: the connection (base URL +
 * credentials) plus the binding's remote identifier, non-secret config and
 * status/priority maps.
 *
 * Capability methods take this rather than a bare {@see ConnectionContext}
 * because acting on a remote system needs to know which remote object and how to
 * translate statuses — that is binding, not connection. Drivers still never
 * touch the Eloquent model; the domain layer builds this from a `ProjectBinding`.
 */
final readonly class BindingContext
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $statusMap
     * @param  array<string, string>  $priorityMap
     */
    public function __construct(
        public ConnectionContext $connection,
        public ?string $remoteIdentifier = null,
        public array $config = [],
        public array $statusMap = [],
        public array $priorityMap = [],
    ) {}

    public function baseUrl(): ?string
    {
        return $this->connection->baseUrl;
    }

    /**
     * @return array<string, mixed>
     */
    public function credentials(): array
    {
        return $this->connection->credentials;
    }
}
