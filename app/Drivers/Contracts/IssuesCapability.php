<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Contracts;

use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\Page;

/**
 * Create/update tickets, comment, look up by key, and translate statuses.
 *
 * Status translation takes the binding-provided map (spec §5): statuses are
 * per-installation, so no driver may hardcode that "Risolto" means resolved.
 */
interface IssuesCapability
{
    /**
     * @return array<string, mixed>|null
     */
    public function lookup(BindingContext $context, string $remoteId): ?array;

    public function list(BindingContext $context, ?string $cursor = null): Page;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function create(BindingContext $context, array $attributes): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function update(BindingContext $context, string $remoteId, array $attributes): array;

    public function comment(BindingContext $context, string $remoteId, string $body): void;

    /**
     * @param  array<string, string>  $statusMap  Remote status name → canonical category.
     */
    public function translateStatus(array $statusMap, string $remoteStatus): ?string;
}
