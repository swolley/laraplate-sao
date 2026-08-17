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

        // Automatic ticket opening from error signals (sao:signals:auto-open).
        // Opt-in: a signal opens a ticket once it reaches `min_occurrences` and
        // has none linked yet. The command is always runnable; only its
        // scheduled registration is gated by `enabled`.
        'auto_open' => [
            'enabled' => (bool) env('SAO_SIGNAL_AUTO_OPEN', false),
            'min_occurrences' => (int) env('SAO_SIGNAL_AUTO_OPEN_MIN', 1),
        ],
    ],

    // Scheduled inbound polling for trackers with no push transport
    // (sao:sync:issues). Safe with nothing configured — a binding must opt in
    // with an inbound sync direction, so an empty install polls nothing. Set
    // SAO_SYNC_ENABLED=false to keep the command manual-only.
    'sync' => [
        'enabled' => (bool) env('SAO_SYNC_ENABLED', true),
        'cron' => (string) env('SAO_SYNC_CRON', '0 * * * *'),
    ],

    // Scheduled connection health probe (sao:connection:health). Opt-in: off by
    // default so no live probe runs unless an operator enables it; the command
    // stays runnable on demand regardless.
    'health' => [
        'enabled' => (bool) env('SAO_HEALTH_SCHEDULE', false),
        'cron' => (string) env('SAO_HEALTH_CRON', '*/15 * * * *'),
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
            Modules\SAO\Drivers\External\YouTrackDriver::class,
            Modules\SAO\Drivers\External\AzureDevOpsDriver::class,
            Modules\SAO\Drivers\External\LinearDriver::class,
            Modules\SAO\Drivers\External\SentryDriver::class,
            Modules\SAO\Drivers\External\GraylogDriver::class,
            Modules\SAO\Drivers\External\GlitchTipDriver::class,
            Modules\SAO\Drivers\External\RollbarDriver::class,
            Modules\SAO\Drivers\External\BugsnagDriver::class,
            Modules\SAO\Drivers\External\HoneybadgerDriver::class,
            Modules\SAO\Drivers\External\GrafanaDriver::class,
            Modules\SAO\Drivers\External\DatadogDriver::class,
            Modules\SAO\Drivers\External\ElasticDriver::class,
            Modules\SAO\Drivers\External\BetterStackDriver::class,
        ],
    ],
];
