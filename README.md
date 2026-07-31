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
-   [Current Bootstrap Status](#current-bootstrap-status)
-   [Roadmap](#roadmap)
-   [Scripts](#scripts)
-   [Contributing](#contributing)
-   [License](#license)

## Description

SAO — **Simply Another Orchestrator** — is a correlation engine between code, errors and work.

It ingests already-selected events from third-party systems, correlates them to a project and a deployed version, and turns them into tracked work. It is not a log aggregator, not an APM and not a CI runner.

With no connection configured, SAO is a complete standalone ticketing system. Version control systems, log sources and external issue trackers are optional, independently switchable integrations provided by drivers.

At this stage the module is intentionally initialized with a minimal structure to support incremental, test-driven development.

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

## Current Bootstrap Status

-   Module metadata (`module.json`) configured with provider registration and the `Core` dependency
-   Service providers scaffolded (`SAOServiceProvider`, `RouteServiceProvider`, `EventServiceProvider`)
-   Base folders for HTTP, config, routes, resources, database, docs and tests in place
-   Composer package scaffolded with the full quality script battery and autoload mappings
-   Quality tooling aligned with the sibling modules (PHPStan, Pint, Rector, Peck, PHPUnit)
-   Independent git repository under `Modules/SAO`, registered as a submodule of the application repo
-   No domain code — phase 0 is scaffolding only

## Roadmap

Design: `docs/superpowers/specs/2026-07-31-sao-module-design.md` in the application repository.

-   Phase 1 — internal ticketing core and base Filament surfaces
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
