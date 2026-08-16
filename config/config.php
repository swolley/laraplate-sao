<?php

declare(strict_types=1);

return [
    'name' => 'SAO',

    // Driver framework (phase 3a). The registry is open: a driver is registered
    // code, a connection is a configured instance of it. Register a driver by
    // listing its class here, or from any service provider's boot() via
    // app(DriverRegistry::class)->register(...) — adding a provider never
    // requires editing SAO. No concrete external driver ships yet.
    //
    // Secrets live on the connection (encrypted, write-only) or an env
    // credential_ref; product-behaviour configuration (thresholds, policy
    // toggles) belongs in Core settings, never here and never in the database
    // as a secret.
    'drivers' => [
        // list<class-string<Modules\SAO\Drivers\Contracts\DriverInterface>>
        'registered' => [
            Modules\SAO\Drivers\External\RedmineDriver::class,
        ],
    ],
];
