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
-   Ticket enrichment (1b): due dates (with `overdue`/`dueWithin` scopes),
    project-scoped labels, watchers (record-only), attachments on the Core-owned
    media library, typed ticket-to-ticket relations (blocks/duplicates/relates),
    and advanced search with saved filters
-   Authorization entirely Laraplate's: permissions through `PermissionName`, and
    row-level visibility through Core's ACL filters — an ACL restricting the view
    permission to one project hides the others, with no mechanism of SAO's own
-   A per-project board (1c): tickets in status-ordered columns, moved through
    the workflow-allowed transitions — a read model over `visible()`, no new table
-   Filament surfaces for projects, statuses, types, workflow schemes, tickets
    (with the 1b enrichment: due date, labels, watchers, attachments, relations
    manager, table filters), the board page, a Connection resource (write-only
    credential), a project Integrations relation manager for bindings, and a
    Signal resource (state-editable, machine fields read-only) with a read-only
    occurrences relation manager

Not yet present: HTML5 drag-and-drop on the board (needs an approved kanban
package; moves are action-based today), and every form of external integration
beyond the phase 3a/3b foundation.

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
| `TicketSearchService` | Filters tickets by a `TicketSearchCriteria` (text, status, type, priority, assignee, label, due window, overdue). It builds strictly on `TicketQueryService::visible()`, so a search never surfaces a hidden ticket. A `SavedFilter` persists a criteria set and round-trips back into a `TicketSearchCriteria` for reapplication. |
| `TicketBoardService` | The board read model (1c): for one project, the status-ordered columns (`BoardColumn`) each carrying its visible tickets. A projection over `visible()` — no board/column/card is persisted. The Filament board page moves cards through `WorkflowService`, offering only workflow-allowed transitions. |

Ticket enrichment (1b) is model-level: `due_at` with `overdue`/`dueWithin`
scopes; a project-scoped `Label` (unique name per project) via a
`sao_ticket_label` pivot; `watchers()` with idempotent `watch()`/`unwatch()`
(notification delivery is out of scope); `attachments` on the Core-owned media
library (`HasMedia`), so SAO depends only on Core; and typed `TicketRelation`
records (`TicketRelationType`: blocks/duplicates/relates) resolved through
`relatedVia()` and `inverselyRelatedVia()`, rejecting self-relations.

History is therefore a **read model** over Core's versioning plus comments — there
is no activity table. Authorization is entirely Laraplate's: permissions through
`PermissionName`, row-level visibility through Core's ACL filters (an ACL that
restricts the view permission to one project hides the others, with no mechanism
of SAO's own).

## Driver framework (phase 3a)

The integration layer's foundation is in place, and six concrete external `issues` drivers ship in `Modules\SAO\Drivers\External` (all registered by default in `config('sao.drivers.registered')`): **Redmine** (`X-Redmine-API-Key`, `remoteIdentifier` = project id), **Jira** Cloud (Basic email+token, JQL by project key), **GitHub** (bearer token, `owner/repo`, `Link`-header pagination), **GitLab** (`PRIVATE-TOKEN`, project id, `X-Next-Page` pagination), **Bitbucket** Cloud (Basic username+app-password, `workspace/repo`, body `next` pagination), and **Gitea** (`Authorization: token`, `owner/repo`, `Link`-header pagination over its GitHub-shaped API). Each maps its remote representation to `NormalizedIssue`, translates statuses only through the binding-provided map (never hardcoded), and passes the `issues` conformance suite against a network-free `Http::fake()`. The three Git hosts (GitHub, GitLab, Bitbucket) additionally serve the **`vcs`** (commits, compare, file-at-ref, open pull/merge request) and **`releases`** (tags, bounded first-tag-containing) capabilities, each passing the `VcsConformance` and `ReleasesConformance` batteries over an `Http::fake()`. A first **`logs`** driver ships too — **Sentry** (push/webhook): it verifies the HMAC-SHA256 delivery signature under the connection secret, unpacks the webhook into a native-keyed event (`carriesNativeGroupKey()` true; `GroupKeyResolver` namespaces it `sentry:<id>`), and feeds the ingest path. Live-instance verification and real push transport remain follow-ups. A **driver** is registered code (`Modules\SAO\Drivers`); a **connection** (`Connection` model) is a configured instance of it. The registry is **open**: `DriverRegistry` is a singleton populated from `config('sao.drivers.registered')` and by any provider's `boot()` — adding a provider never requires editing SAO. Duplicate keys throw so a collision surfaces at boot.

A driver implements `DriverInterface` (key, capabilities, ingest modes, configuration schema, health check) plus one or more per-capability contracts — `IssuesCapability`, `VcsCapability`, `LogsCapability`, `ReleasesCapability`. Domain services depend on those capabilities, never on a concrete driver. Drivers operate on a resolved `ConnectionContext` (base URL + credentials), never on the Eloquent model, so `app/Drivers` stays free of persistence. Every capability list returns a `Page` and callers follow `nextCursor` to completion; the conformance suite includes a multi-page fixture so a first-page-only read fails. Status translation takes the binding-provided map, never a hardcoded one.

**Credentials (F4).** A `Connection` holds only non-secret coordinates plus its secret via one of two paths: an encrypted, write-only `credential` column, or a `credential_ref` env/config key that overrides it. `ConnectionCredentialResolver` is the single path from a connection to its secret (ref wins, else the decrypted column, else it throws); the raw secret is never rendered back to a UI and never stored in Core settings. A connection may expose only a subset of its driver's capabilities, enforced on save.

**Conformance (spec §12).** `tests/Support/Conformance/*` are the reusable batteries a driver of a capability must pass; `InMemoryDriver` (test support) is a network-free reference driver that passes `issues` and `releases` and proves the registry → connection → resolver → capability stack runs offline. A driver is done when it passes conformance, not when it works.

## Roadmap

Design: `docs/superpowers/specs/2026-07-31-sao-module-design.md` in the application repository.

-   Phase 1a — internal ticketing core and base Filament surfaces (**done**)
-   Phase 1b — labels, watchers, attachments, due dates, ticket relations, search and saved filters (**done**, including the Filament surfaces)
-   Phase 1c — board: status-ordered columns per project, moves through the workflow (**done**; HTML5 drag-and-drop deferred, needs an approved kanban package)
-   Phase 2 — shared fingerprinting in Core, error signals, internal log source, loop protection (**done** at the ingest/grouping level: Core's `Logging\Fingerprint` rule chain + `Fingerprinter` (line excluded, value-position numerics), SAO's `PayloadFrameResolver`, the `Signal`/`SignalOccurrence`/`SignalAlias` models, `GroupKeyResolver` + `SignalIngestService`, and loop protection via `PipelineContext` + `InternalLogSource` + a per-group rate limiter. Real webhook transport/source profiles are phase 4; ticket auto-open from a signal is phase 6)
-   Phase 3 — driver framework, connections, capabilities and external issue trackers (3a foundation **done**: registry, contracts, `Connection`, credential resolver, conformance suite; 3b **done**: `ProjectBinding`, `TicketLink`, the internal issues driver, `IssueSyncService`, and five concrete external `issues` drivers — **Redmine, Jira, GitHub, GitLab, Bitbucket** — each passing the issues conformance suite over an `Http::fake()`; 3b-ui **done**: a Filament Connection resource (write-only encrypted credential) and a project Integrations relation manager for bindings. Live-instance verification and push/webhook ingest remain follow-ups)
-   Phase 5 — `vcs`/`releases` capabilities on the Git-host drivers (GitHub, GitLab, Bitbucket): commits/compare/file-at-ref/open-PR and tags/first-tag-containing, each passing `VcsConformance` + `ReleasesConformance` over an `Http::fake()` (**done**). Phase 5b adds the correlation data (**done**): `ChangeRef` (a commit/PR/tag linked to a ticket), `Release`/`ReleaseTag`/`TicketRelease` (a product version, the stable/candidate tags realizing it, and tickets attributed as promised/shipped independent of workflow status), and `Environment` + `DeployCensusService` (passive `observe`, active `recordProbe`, TTL-based `isStale`, and a `census(project, ttl)` read model that answers "what runs where" with an honest freshness). Filament CRUD surfaces for releases (with a tags relation manager) and environments ship under the SAO group; live probe transport remains a follow-up
-   Phase 4 — source profiles, generic webhook ingest and replay (**done** at the ingest level: `SourceProfile` (matchers + dot-path field bindings), `IngestEvent` (every delivery recorded with an explicit outcome — auditable silence), `WebhookIngestService` (dedupe by delivery id, profile selection, normalization, ordered `CorrelationRuleset` recording the winning rule, hand-off to `SignalIngestService`), and a pure dry-run `IngestReplayService`. Concrete Graylog/Sentry `logs` drivers and the ingest-events Filament surface are follow-ups)
-   Phase 5 — version control and release capabilities, code-to-work references, version census
-   Phase 6 — fix propagation and evidence-based closure policies (**done** at the deterministic-decision level): the persisted facts (`Signal.ticket_id`, `ChangeRef.merged_at`), `FixStatusResolver` (merged PR / shipped-release / where-deployed → the "already fixed on dev, deploy missing" read model), six independently-testable closure conditions over a pure `ClosureContext` (a candidate never satisfies `fix_released`), project-scoped `ClosurePolicy` (conditions as `{key, config}` json + action, `propose` the prudent default), `ClosureEvaluator` (AND semantics, empty set never satisfies), `ClosureAudit` + `ClosureAuditService` (records the "closed because"; a recurrence reopens and marks premature with "returned after"), and `TimeToTruthService`. `ClosureApplicationService` turns a satisfied policy into action: it resolves the context, evaluates the policy, and for a `close` policy moves the ticket to a `closed` status **through `WorkflowService`** (the one path to a status change — automation never writes `ticket_status_id` directly, and only a permission-free transition to a `closed`, never `rejected`, status is used), recording the `ClosureAudit`. A `propose` policy records the audit without moving the ticket; `notify_only` does neither. A Filament CRUD surface for `ClosurePolicy` (conditions composed through a repeater) and a **read-only** surface for `ClosureAudit` (list + view, no create/edit — the "closed because" and premature-closure "returned after" trail stays machine-written) ship under the SAO group. Ownership-suggestion persistence/UI remains a follow-up
-   Phase 7 — second driver wave (**done**): the **Gitea** `issues` driver (GitHub-shaped API, passing the issues conformance suite over an `Http::fake()`) and the first **`logs`** driver, **Sentry** (push/webhook: HMAC-SHA256 signature verification, native-keyed unpack namespaced `sentry:<id>`). Both registered by default
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
