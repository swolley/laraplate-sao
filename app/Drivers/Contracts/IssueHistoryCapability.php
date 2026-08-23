<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Contracts;

use Modules\SAO\Drivers\Support\BindingContext;

/**
 * Read one issue's comment thread and attachments for a migration import.
 *
 * An optional extension of `issues`, like {@see BlameCapability} is of `vcs`: not
 * every tracker exposes comments and files the same way, so a driver implements
 * this only where the host does. The {@see \Modules\SAO\Services\TrackerImportService}
 * depends on this contract, not a concrete driver, and simply imports no history
 * for a connection whose driver does not implement it.
 *
 * The driver returns already-fetched attachment bytes rather than a bare URL:
 * downloading a private tracker's file needs the connection credentials, which
 * only the driver holds, so authentication stays inside the driver and the
 * importer never makes an unauthenticated request.
 */
interface IssueHistoryCapability
{
    /**
     * The issue's comments, oldest first. `remote_id` is the tracker's stable
     * comment id — the importer's idempotency key, so a re-run adds none twice.
     *
     * @return list<array{remote_id: string, body: string, author?: string|null}>
     */
    public function comments(BindingContext $context, string $remoteId): array;

    /**
     * The issue's attachments with their bytes already downloaded. `remote_id` is
     * the tracker's stable attachment id — the importer's idempotency key.
     *
     * @return list<array{remote_id: string, filename: string, contents: string}>
     */
    public function attachments(BindingContext $context, string $remoteId): array;
}
