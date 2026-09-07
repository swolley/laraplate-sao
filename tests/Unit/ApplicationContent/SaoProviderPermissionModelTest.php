<?php

declare(strict_types=1);

use Modules\Core\ApplicationContent\Contracts\ProvidesPermissionModel;
use Modules\Core\Support\PermissionName;
use Modules\SAO\ApplicationContent\SaoApplicationContentRetrievalProvider;
use Modules\SAO\ApplicationContent\SaoTicketEvidenceProjector;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Tests\TestCase;

uses(TestCase::class);

it('declares the Ticket model so retrieval is gated on the real table permission', function (): void {
    $provider = new SaoApplicationContentRetrievalProvider(
        app(Modules\Core\Search\Services\AdvancedSearchService::class),
        app(Modules\SAO\Services\TicketQueryService::class),
        app(Modules\Core\Services\Crud\QueryBuilder::class),
        new SaoTicketEvidenceProjector,
    );

    expect($provider)->toBeInstanceOf(ProvidesPermissionModel::class)
        ->and($provider->permissionModel())->toBe(Ticket::class)
        ->and(PermissionName::forClass(Ticket::class, 'select'))->toBe('default.sao_tickets.select');
});
