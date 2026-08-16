# SAO module glossary

Canonical English names for SAO concepts. Use these terms in code, APIs, tests and cross-module
documentation. The vocabulary is fixed here before the domain exists, so that later phases inherit
it rather than invent parallel names.

## Module scope

| Term | Meaning |
|------|---------|
| **SAO** | Simply Another Orchestrator: a correlation engine between code, errors and work. Not a log aggregator, not an APM, not a CI runner. |
| **`sao_` prefix** | All SAO tables use this prefix. Names like `connections`, `projects` and `tickets` are too generic to sit unprefixed beside Core/CMS/ERP. |
| **Core dependency** | SAO depends on Core for fingerprint normalization, ACL, CRUD and Filament foundations. The dependency is unidirectional: SAO → Core. |
| **Standalone use** | An installation with zero connections configured. SAO is then a complete ticketing system. This is the default, not a degraded mode. |

## Integration

| Term | Meaning |
|------|---------|
| **Driver** | Registered code that knows how to talk to one external system. Registered in an open registry: a third-party package can add one without modifying SAO. |
| **Connection** | A configured instance of a driver: base URL, encrypted credentials, health state, declared capabilities. |
| **Capability** | What a connection can do: `issues`, `vcs`, `logs`, `releases`. One connection may expose several — a GitHub connection exposes all but `logs`. |
| **Ingest mode** | How events reach SAO from a driver: `push` (webhook), `pull` (polling), `in_process` (no transport). Each driver declares which it supports. |
| **Conformance suite** | The shared test battery every driver of a given capability must pass. A driver is done when it passes conformance, not when it works. |

## Projects and deployment

| Term | Meaning |
|------|---------|
| **Project** | The correlation anchor: a tracked software project. Holds no URLs or credentials of its own — only bindings. |
| **ProjectBinding** | A link from a project to a connection for one capability, plus binding-scoped configuration: sync direction, status map, priority map. |
| **Sync direction** | Who owns a ticket. `mirror`: SAO owns it, the external system receives writes. `shadow`: the external system owns it, SAO reads and correlates. A project with no `issues` binding is local. |
| **Status map** | The translation between canonical statuses and one specific remote installation's statuses. Lives on the binding, never on the driver: Redmine statuses are per-installation and Jira workflows per-project. |
| **Environment** | A deployment target of a project (`production`, `staging`, …), unique by name per project, recording `current_version` last seen running and `last_seen_at`. |
| **Environment liveness** | The last time an environment was observed sending anything. Absence of errors is evidence only when the source was demonstrably alive. |
| **Deploy census** | `DeployCensusService`'s answer to "what runs where": one row per environment (version + freshness). Written by two feeds — `observe()` for a passive signal and `recordProbe()` for an active check, both stamping `last_seen_at` — and read via `census(project, ttl)`. |
| **Staleness** | Whether what we know about an environment is older than a caller-chosen TTL. An environment never seen is stale by definition, so the census never claims certainty it lacks. |
| **Release** | A product version of a project, named as its stable label, with status `announced` (being assembled) or `shipped` (a stable tag realizing it exists). |
| **ReleaseTag** | A concrete VCS tag realizing a release, `stable` (shippable) or `candidate` (an RC keeping a testable reference for staging). |
| **TicketRelease** | The attribution of a ticket to a release as `promised` or `shipped`. The pair is unique and the state is deliberately independent of the ticket's own workflow status. |

## Ingest

| Term | Meaning |
|------|---------|
| **IngestEvent** | One raw received event: connection, delivery id, payload, status, outcome. The record of what came in. |
| **Delivery id** | The identifier a source assigns to one delivery. Unique per connection; the basis of retry idempotency. |
| **SourceProfile** | A normalization profile stored in the database: matchers plus JSONPath field bindings. Lets a new source be supported by configuration, without code. |
| **Matcher** | The rule that selects which profile applies to an incoming payload. |
| **Field binding** | A JSONPath expression mapping one payload location to one canonical field. |
| **Canonical field** | A normalized field name shared by all sources, independent of any source's payload shape. |
| **Dry-run replay** | Replaying a retained sample payload against a modified profile to see what would have happened, without acting. |
| **Correlation ruleset** | The ordered, inspectable rules that attach an event to a project. Every event records which rule won. |
| **Pipeline origin marker** | The runtime marker set while an ingest, normalization or synchronization job runs. The internal log source discards records carrying it, which is what makes a self-feeding loop impossible. |

## Error grouping

| Term | Meaning |
|------|---------|
| **Fingerprint** | The hash computed from a normalized error: kind, module, class, normalized file, function, normalized message. The line number is metadata, deliberately not an ingredient. |
| **Group key** | The identity of an error group. Either native or computed. |
| **Native group key** | A grouping key the source already provides — Core's own fingerprint, a Sentry issue id. Namespaced per source (`core:…`, `sentry:…`) so keys from different systems cannot collide. SAO computes its own only in their absence. |
| **`algo_version`** | The version of the fingerprint algorithm that produced a group key. Stored from the first migration; adding it later would mean backfilling unknown values. |
| **Signal** | An error group: group key, algorithm version, project, counters, first and last seen, state, affected versions. |
| **SignalOccurrence** | One individual occurrence of a signal, with configurable retention. |
| **SignalAlias** | A superseded group key pointing at its signal. What lets the fingerprint algorithm evolve without splitting history. |

## Work

| Term | Meaning |
|------|---------|
| **Ticket** | The canonical unit of work: title, body, canonical status, priority, assignee, comments. Exists with or without an external counterpart. |
| **TicketLink** | The link between a ticket and its counterpart in an external tracker. No link means an internal ticket. |
| **Internal ticket** | A ticket with no `TicketLink`. The default, and the reason standalone use needs no special code path. |
| **ChangeRef** | The link between a code artefact (commit, pull request, tag) and a ticket, with the source that produced it. |
| **Idempotency key** | The persisted key carried by every outbound write, so a retry can never produce a second comment or a second ticket. Trackers rarely offer idempotent write APIs; the guarantee lives on our side. |
| **Due date** | A ticket's `due_at`. The `overdue` scope selects past-due tickets not in a terminal status; `dueWithin` selects tickets due in the next N days. |
| **Label** | A project-scoped tag on a ticket (unique name per project). Attached many-to-many through `sao_ticket_label`. |
| **Watcher** | A user following a ticket. Record-only in 1b: `watch()`/`unwatch()` are idempotent; notification delivery is out of scope. |
| **Attachment** | A file on a ticket's `attachments` media collection, stored in the Core-owned media library (`vend_media`). SAO uses Core's `HasMedia`, depending only on Core. |
| **TicketRelation** | A typed link between two tickets (`TicketRelationType`: blocks/duplicates/relates). Directional types read differently per end (`blocks` inverts to "blocked by"); `relates` is symmetric. Self-relations are rejected. |
| **TicketSearchCriteria** | An immutable, JSON-serialisable description of a ticket search (text, status, type, priority, assignee, label, due window, overdue). |
| **TicketSearchService** | Turns a `TicketSearchCriteria` into a query built strictly on `TicketQueryService::visible()`, so a search never surfaces a hidden ticket. |
| **SavedFilter** | A user's persisted `TicketSearchCriteria`, optionally scoped to one project. Round-trips back into criteria for reapplication. |
| **Board** | A per-project view of tickets in status-ordered columns (1c). A read model (`TicketBoardService` → `BoardColumn`) over the ACL-scoped visible query; cards move only through workflow-allowed transitions via `WorkflowService`. No board/column/card is persisted. |
| **BoardColumn** | One column of the board: a `TicketStatus` and the visible tickets in it. An empty status is still a column. |

## Automation

| Term | Meaning |
|------|---------|
| **ClosurePolicy** | A per-project set of closure conditions (stored as `{key, config}` json) combined with AND, plus the action (`ClosureAction`: close / propose / notify_only) taken when they all hold. `propose` is the prudent default on `shadow` external bindings. |
| **Closure condition** | One independently testable predicate over verifiable facts: `pull_request_merged`, `no_recurrence_for`, `fix_released`, `fix_deployed_there`, `resolved_for`, `internal_tickets_only`. Built by `ClosureConditionRegistry` from the policy json. |
| **ClosureContext** | The assembled, verifiable facts a policy is evaluated against, with `now` injected so a decision is deterministic and reproducible. Conditions are pure functions of it. |
| **ClosureDecision** | `ClosureEvaluator`'s output: the action, whether every condition held (AND; an empty set never holds), and the per-condition outcomes with evidence — the "closed because". |
| **ClosureAudit** | The record of an automatic or proposed closure: which conditions held with what evidence, and — on reopen — the "returned after" (duration, environment, occurrence) that flags a **premature closure**. |
| **Closure application** | `ClosureApplicationService`: evaluates a policy against a ticket and, when satisfied, acts — a `close` policy moves the ticket to a `closed` status through `WorkflowService` (never writing the status directly) and records the audit; `propose` records only; `notify_only` does neither. |
| **Premature closure** | An automatic closure invalidated by the signal reappearing. Recorded as such, and the data that says whether configured durations are tuned correctly. |
| **Ownership suggestion** | `OwnershipSuggestion` (via `OwnershipSuggestionService`): a deterministic proposal of a ticket's owner from code evidence — strongest rule first (`OwnershipRule`: codeowners > blame > recent touch > path), then score. A proposal only: SAO never applies an assignee automatically (D14). |
| **Ownership evidence resolvers** | The services that turn `vcs` reads into `OwnershipEvidence`: `CodeownersOwnershipResolver` (CODEOWNERS patterns, last-match-wins), `RecentTouchOwnershipResolver` (commit count per author over a range) and `BlameConcentrationOwnershipResolver` (owned-line count per author across the touched files, via the optional `BlameCapability` — GitHub-only). All resolve identities (handle or email) to user ids through an injected identity map and skip the unmappable. |
| **Fix propagation** | `FixStatusResolver`'s deterministic read of whether a fix's PR is merged, a **shipped** release carries it, and which environments run that version — the "already fixed on dev, deploy missing" answer. |
| **Time-to-truth** | `TimeToTruthService`'s lag, from a signal's first sighting, until the fix was merged, a deploy gap was knowable, and (if it happened) a premature closure was reopened. |
