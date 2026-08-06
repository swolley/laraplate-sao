<p>&nbsp;</p>
<p align="center">
	<a href="https://github.com/swolley" target="_blank">
		<img src="https://raw.githubusercontent.com/swolley/images/refs/heads/master/logo_laraplate.png?raw=true" width="400" alt="Laraplate Logo" />
    </a>
</p>
<p>&nbsp;</p>

> ⚠️ **Caution**: This package is a **work in progress**. **Don't use this in production or use at your own risk**—no guarantees are provided.

## Table of Contents

-   [Description](#description)
-   [Installation](#installation)
-   [Configuration](#configuration)
-   [Current Status](#current-status)
-   [Roadmap](#roadmap)
-   [Scripts](#scripts)
-   [Contributing](#contributing)
-   [License](#license)

## Description

SAO — **Simply Another Orchestrator** — is a correlation engine between code, errors and work.

It ingests already-selected events from third-party systems, correlates them to a project and a deployed version, and turns them into tracked work. It is not a log aggregator, not an APM and not a CI runner.

With no connection configured, SAO is a complete standalone ticketing system. Version control systems, log sources and external issue trackers are optional, independently switchable integrations provided by drivers.

The roadmap is delivered in slices; the internal ticketing core is complete and the integration layer is not yet started.

## Installation

If you want to add this module to your project, you can use the `joshbrw/laravel-module-installer` package.

Add repository to your `composer.json` file:

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://github.com/swolley/laraplate-core.git"
    },
    {
        "type": "composer",
        "url": "https://github.com/swolley/laraplate-sao.git"
    }
]
```

```bash
composer require joshbrw/laravel-module-installer swolley/laraplate-core swolley/laraplate-sao
```

Then, you can install the module by running the following command:

```bash
php artisan module:install Core
php artisan module:install SAO
```

## Configuration

The module configuration is automatically mapped as `sao.*` when the module is active.
Configuration file: `Modules/SAO/config/config.php`.

> The effective set of environment variables will be introduced with the first domain phase.

## Current Status

Slice 1a — the internal ticketing core — is implemented. SAO is usable as a
standalone tracker with **no connection to any external system**, because none
exists yet.

-   Projects with an immutable key prefix and per-project ticket keys (`SAO-123`),
    allocated under a row lock
-   Global ticket statuses carrying a canonical category — open, in progress,
    resolved, closed, rejected — which later phases map against instead of names
-   Workflow schemes shared across ticket types, with transitions enforced by the
    domain service rather than merely hidden in the interface, and an override
    gated by its own permission
-   Ticket types enabled per project, optionally overriding the workflow scheme
    for one project alone
-   Tickets with optimistic locking, comments distinguishing people from
    automation, and a timeline merging comments with Core's version history
-   Authorization entirely Laraplate's: permissions through `PermissionName`, and
    row-level visibility through Core's ACL filters — an ACL restricting the view
    permission to one project hides the others, with no mechanism of SAO's own
-   Filament surfaces for projects, statuses, types, workflow schemes and tickets

Not yet present: labels, watchers, attachments, due dates, ticket relations and
the board (slices 1b and 1c), and every form of external integration.

## Roadmap

Design: `docs/superpowers/specs/2026-07-31-sao-module-design.md` in the application repository.

-   Phase 1a — internal ticketing core and base Filament surfaces (**done**)
-   Phase 1b — labels, watchers, attachments, due dates, ticket relations, search
-   Phase 1c — kanban board
-   Phase 2 — shared fingerprinting in Core, error signals, internal log source, loop protection
-   Phase 3 — driver framework, connections, capabilities and the first external issue tracker
-   Phase 4 — source profiles, generic webhook ingest and replay
-   Phase 5 — version control and release capabilities, code-to-work references, version census
-   Phase 6 — fix propagation and evidence-based closure policies
-   Phase 7 — second driver wave
-   Phase 8 — AI, as a hard module requirement
-   Phase 9 — Vue surfaces

## Scripts

Run commands from the **SAO module root** after `composer install`.

```bash
# Run all tests and quality checks
composer test

# Run specific checks
composer test:unit
composer test:type-coverage
composer test:lint
composer test:types
composer test:refactor
```

```bash
# Local formatting (dirty files only from project root)
vendor/bin/pint --dirty
```

## Contributing

If you want to contribute to this project, follow these steps:

1. Fork the repository.
2. Create a new branch for your feature or correction.
3. Send a pull request.

## License

SAO Module is open-sourced software licensed under the [GNU AGPL v3](https://www.gnu.org/licenses/agpl-3.0.html).
