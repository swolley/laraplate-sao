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

## How it works (developer)

Configuration is **data, not code**: statuses, workflow schemes, transitions and
ticket types are rows, so a deployment can reshape the process without a release.
A ticket's permitted moves come from the workflow scheme of its `(project, type)`
pair, with an optional per-project scheme override gated by its own permission.

All orchestration lives in domain services, never in a UI layer, so the Filament
panel, a future API and phase 2's automation share one enforcement path:

| Service | Responsibility |
|---------|----------------|
| `TicketCreationService::open()` | The single path that opens a ticket. |
| `WorkflowService` | The only path to a status change; transitions are enforced here (not merely hidden in the UI) because the API and automation move tickets too. |
| `TicketKeyAllocator` | Allocates the per-project key (`SAO-123`) under `lockForUpdate`; gaps are accepted (a rolled-back transaction loses its number) rather than serializing every creation. |
| `TicketQueryService` | The only sanctioned read path. Core's `HasACL` global scope is an unimplemented TODO, so raw `Ticket::query()` would silently bypass row-level visibility — every read goes through here or Core's CRUD layer. |
| `TicketTimelineService` | Merges comments and Core version history into one ordered stream; the single place that knows where history comes from, so a future activity table would replace only its second half. |

History is therefore a **read model** over Core's versioning plus comments — there
is no activity table. Authorization is entirely Laraplate's: permissions through
`PermissionName`, row-level visibility through Core's ACL filters (an ACL that
restricts the view permission to one project hides the others, with no mechanism
of SAO's own).

## Driver framework (phase 3a)

The integration layer's foundation is in place; no concrete external driver ships yet. A **driver** is registered code (`Modules\SAO\Drivers`); a **connection** (`Connection` model) is a configured instance of it. The registry is **open**: `DriverRegistry` is a singleton populated from `config('sao.drivers.registered')` and by any provider's `boot()` — adding a provider never requires editing SAO. Duplicate keys throw so a collision surfaces at boot.

A driver implements `DriverInterface` (key, capabilities, ingest modes, configuration schema, health check) plus one or more per-capability contracts — `IssuesCapability`, `VcsCapability`, `LogsCapability`, `ReleasesCapability`. Domain services depend on those capabilities, never on a concrete driver. Drivers operate on a resolved `ConnectionContext` (base URL + credentials), never on the Eloquent model, so `app/Drivers` stays free of persistence. Every capability list returns a `Page` and callers follow `nextCursor` to completion; the conformance suite includes a multi-page fixture so a first-page-only read fails. Status translation takes the binding-provided map, never a hardcoded one.

**Credentials (F4).** A `Connection` holds only non-secret coordinates plus its secret via one of two paths: an encrypted, write-only `credential` column, or a `credential_ref` env/config key that overrides it. `ConnectionCredentialResolver` is the single path from a connection to its secret (ref wins, else the decrypted column, else it throws); the raw secret is never rendered back to a UI and never stored in Core settings. A connection may expose only a subset of its driver's capabilities, enforced on save.

**Conformance (spec §12).** `tests/Support/Conformance/*` are the reusable batteries a driver of a capability must pass; `InMemoryDriver` (test support) is a network-free reference driver that passes `issues` and `releases` and proves the registry → connection → resolver → capability stack runs offline. A driver is done when it passes conformance, not when it works.

## Roadmap

Design: `docs/superpowers/specs/2026-07-31-sao-module-design.md` in the application repository.

-   Phase 1a — internal ticketing core and base Filament surfaces (**done**)
-   Phase 1b — labels, watchers, attachments, due dates, ticket relations, search
-   Phase 1c — kanban board
-   Phase 2 — shared fingerprinting in Core, error signals, internal log source, loop protection
-   Phase 3 — driver framework, connections, capabilities and the first external issue tracker (3a foundation **done**: registry, contracts, `Connection`, credential resolver, conformance suite; 3b adds the first concrete driver — Redmine — and ticket sync)
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
