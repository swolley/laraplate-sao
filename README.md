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

Environment variables (all optional; the defaults are production-safe):

| Variable | Default | Purpose |
|----------|---------|---------|
| `SAO_SIGNAL_MAX_OCCURRENCES` | `1000` | Per-group occurrence cap within the rolling window (loop protection, layer 2). |
| `SAO_SIGNAL_WINDOW_MINUTES` | `60` | Length of that rolling window, in minutes. |
| `SAO_SIGNAL_AUTO_OPEN` | `false` | Whether `sao:signals:auto-open` is registered on the scheduler. The command is always runnable manually. |
| `SAO_SIGNAL_AUTO_OPEN_MIN` | `1` | Minimum occurrences before a signal auto-opens a ticket. |
| `SAO_SYNC_ENABLED` | `true` | Whether `sao:sync:issues` is registered on the scheduler. Set `false` to keep inbound polling manual-only. |
| `SAO_SYNC_CRON` | `0 * * * *` | Cron expression for the scheduled inbound issue poll. |
| `SAO_HEALTH_SCHEDULE` | `false` | Whether `sao:connection:health` runs on the scheduler. The command is always runnable manually. |
| `SAO_HEALTH_CRON` | `*/15 * * * *` | Cron expression for the scheduled connection health probe. |
| `SAO_RELEASE_HEALTH_WINDOW_DAYS` | `7` | How many days after a release its signals are attributed to it when judging release health. |
| `SAO_RELEASE_HEALTH_REGRESSION_FACTOR` | `1.5` | A pre-existing signal counts as regressed only when its in-window count exceeds its baseline count by this factor. |
| `SAO_RELEASE_HEALTH_REGRESSION_MIN` | `3` | Minimum in-window occurrences before a pre-existing signal can be flagged as regressed (noise floor). |
| `SAO_RELEASE_HEALTH_REGRESSED_NEW_SIGNALS` | `3` | Number of brand-new signals in the window that on their own tip the verdict to `regressed`. |
| `SAO_CLOSURE_AUTO_CLOSE` | `false` | When `true`, `sao:closure:run` lets a satisfied `close` policy actually close the ticket; when `false` it only proposes (records a `ClosureAudit`, no state change). |

## Code-to-work attribution & closure

SAO derives "is this ticket actually fixed, and everywhere?" from evidence, not a
human flag. It reads ticket keys out of commit messages and merged pull-request
text and records a `ChangeRef` per (ticket, artefact): a closing verb
(`fixes|closes|resolves…`, configurable via `sao.attribution.closing_verbs`)
marks a **fix**, any other reference a **mention**. Only fixes count as resolution
evidence. A fixing commit is attributed to the release that carries it via the
`releases` capability (`firstTagContaining`), classifying the tag as stable →
`shipped` or a candidate (RC) → `promised` by a semver heuristic
(`sao.attribution.prerelease_markers`). The release **version is always the
normalized stable label** (`v1.4.0-rc.1` → `1.4.0`), so a candidate records the
future stable version — the release stays `announced` (the stable tag does not
exist yet), realized only by candidate tags. `php artisan sao:releases:sync
{connection?}` lists a `releases` binding's tags and **promotes** such a release
to `shipped` once its stable tag is actually cut — deterministically, rather than
depending on which tag `firstTagContaining` returns.

Two transports feed the same writer:

- **Pull** — `php artisan sao:vcs:scan {connection?} {--range=main}` walks a `vcs`
  binding's commits; idempotent, so it also backfills history.
- **Push** — a `code` webhook at `POST api/v1/webhooks/{connection}` (the shared
  route, branched by driver capability). Drivers: a generic `webhook-code` (token
  in `X-Code-Token`) and `github-pull-request` (GitHub's `pull_request` webhook,
  HMAC-verified, only *merged* PRs count).

`php artisan sao:closure:run {project?} {--env=}` evaluates active closure policies
over non-terminal tickets. By default it only **proposes** a close; set
`SAO_CLOSURE_AUTO_CLOSE=true` to let a satisfied `close` policy actually close the
ticket (through `WorkflowService`, audited and auto-reversible on recurrence).

## Migrating from an external tracker

To switch a project from an external issue tracker to Laraplate, import its
history through the existing `issues` binding:

```bash
php artisan sao:tracker:import "Acme Jira" --project="Web" --scope=open --cutover
```

- `--scope=open` imports only issues still active — an issue whose remote status
  maps (through the binding's `status_map`) to a terminal category
  (closed/rejected) is skipped; `--scope=all` (default) imports everything. An
  unmapped remote status is treated as open, so nothing active is dropped.
- The import is **idempotent** (matched by `TicketLink`), so it is safe to re-run
  and effectively resumable; `--queue` dispatches one background job per binding.
- `--cutover` makes SAO authoritative afterwards by flipping the binding's sync
  direction — `disabled` by default (external tracker abandoned) or
  `--cutover-direction=outbound` to keep pushing changes back during a transition.
  The `TicketLink`s are kept as provenance.

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
-   Release health & deploy ingest — post-hoc, correlation only (never a rollout
    gate). `ReleaseHealthService` judges whether a release introduced new or
    worsening signals versus the previous release over an equal window;
    `DeploymentIngestService` records a durable, idempotent deploy history
    (`sao_deployments`) that makes the environment version census a projection and
    gives release health a precise per-deploy anchor. Plan:
    `docs/plans/release-health-and-deploy-ingest.md`.

### Deployments

A deployment is recorded by the ingest — never by hand — deduped by
`(connection, external_id)` so a re-delivery advances the same record. A terminal
`succeeded` deployment advances the environment's `current_version`; its
`finished_at` is the window anchor release health reads from. Record one without
any external integration (CLI-only CD steps, replay) with:

```bash
php artisan sao:deploy:record {project} {version} --env=production --status=succeeded --external-id=ci-run-42
```

Deploys can also arrive as a **push webhook** through the `deploy` capability at
`POST api/v1/webhooks/{connection}` (the same endpoint the `logs` push uses; the
route branches by the connection's driver capability). Two drivers ship: a generic
`webhook-deploy` (shared token in the `X-Deploy-Token` header) and
`github-deployment` (GitHub's `deployment_status` webhook, HMAC-SHA256 signature).
Each delivery is verified by the driver, unpacked into deploy events, recorded via
the same `DeploymentIngestService` (deduped, census-projecting), and audited as an
`IngestEvent`. The secret lives on the connection.

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
