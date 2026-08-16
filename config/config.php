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
    // Signal ingest guards (phase 2). The per-group rate limiter caps how many
    // occurrences of one signal are recorded within a rolling window — layer 2
    // of the loop protection, so a fast-looping error inside an observed app
    // cannot flood the store.
    'signals' => [
        'max_occurrences_per_window' => (int) env('SAO_SIGNAL_MAX_OCCURRENCES', 1000),
        'window_minutes' => (int) env('SAO_SIGNAL_WINDOW_MINUTES', 60),
    ],

    'drivers' => [
        // list<class-string<Modules\SAO\Drivers\Contracts\DriverInterface>>
        'registered' => [
            Modules\SAO\Drivers\External\RedmineDriver::class,
            Modules\SAO\Drivers\External\JiraDriver::class,
            Modules\SAO\Drivers\External\GitHubDriver::class,
            Modules\SAO\Drivers\External\GitLabDriver::class,
            Modules\SAO\Drivers\External\BitbucketDriver::class,
            Modules\SAO\Drivers\External\GiteaDriver::class,
        ],
    ],
];
