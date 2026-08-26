# Changelog

All notable changes to this project will be documented in this file.

## [unreleased]

### 🐛 Bug Fixes

- *(seeder)* Enhance DevSAODatabaseSeeder to handle corrupt permission cache

## [1.0.0] - 2026-08-25

### 🚀 Features

- *(sao)* Add the ticket surface and close slice 1a
- *(sao)* Capability and ingest-mode enums (phase 3a task 1)
- *(sao)* Driver base and per-capability contracts (phase 3a task 2)
- *(sao)* Open driver registry (phase 3a task 3)
- *(sao)* Connection model with encrypted credentials and env override (phase 3a task 4)
- *(sao)* Connection credential resolver (phase 3a task 5)
- *(sao)* Register driver framework and document it (phase 3a task 7)
- *(sao)* Sync-direction enum and normalized issue value object (phase 3b task 1)
- *(sao)* Non-secret config column on connections (phase 3b task 3)
- *(sao)* Project binding model with binding context (phase 3b task 4)
- *(sao)* Ticket link model (phase 3b task 5)
- *(sao)* Internal issues driver over the ticket domain (phase 3b task 6)
- *(sao)* Idempotent direction-aware issue sync (phase 3b task 7)
- *(sao)* Ticket due dates with overdue/due-within scopes
- *(sao)* Project-scoped ticket labels
- *(sao)* Ticket watchers
- *(sao)* Ticket attachments via media library
- *(sao)* Typed ticket-to-ticket relations
- *(sao)* Ticket search and saved filters
- *(sao)* Filament surfaces for ticket enrichment
- *(sao)* Ticket board read model
- *(sao)* Filament ticket board page
- *(sao)* Redmine external issues driver
- *(sao)* Jira external issues driver
- *(sao)* Github external issues driver
- *(sao)* Gitlab external issues driver
- *(sao)* Bitbucket external issues driver
- *(sao)* Filament connection resource (3b-ui)
- *(sao)* Project bindings filament surface (3b-ui)
- *(sao)* Github vcs and releases capabilities
- *(sao)* Gitlab vcs and releases capabilities
- *(sao)* Bitbucket vcs and releases capabilities
- *(sao)* Payload frame resolver for received errors
- *(sao)* Signal, occurrence and alias models
- *(sao)* Group-key resolution and signal ingest
- *(sao)* Loop protection via pipeline marker and rate limit
- *(sao)* Filament signal resource and occurrences
- *(sao)* Ingest event and source profile models
- *(sao)* Webhook ingest, correlation and dry-run replay
- *(sao)* Code-to-work change references
- *(sao)* Releases, release tags and ticket-release attribution
- *(sao)* Environments and deploy census
- *(sao)* Fix propagation read model
- *(sao)* Evidence-based closure conditions
- *(sao)* Closure policies and context resolver
- *(sao)* Closure audit and premature-closure memory
- *(sao)* Time-to-truth metric
- *(sao)* Gitea issues driver
- *(sao)* Sentry logs driver
- *(sao)* Filament surfaces for releases, environments and closure policies
- *(sao)* Read-only filament surface for closure audits
- *(sao)* Apply closure decisions to the ticket workflow
- *(sao)* Deterministic ownership suggestion with read-only surface
- *(sao)* Codeowners ownership-evidence resolver
- *(sao)* Normalize the commit author in vcs reads
- *(sao)* Recent-touch ownership-evidence resolver
- *(sao)* Blame capability and blame-concentration ownership resolver
- *(sao)* Contributor identity directory as the ownership identity-map source
- *(sao)* Ownership suggestion coordinator
- *(sao)* Discover a pull request's changed files for ownership
- *(sao)* Phase 8 — AI-phrased ownership suggestions
- *(sao)* Request optional AI phrasing through Core's event seam
- *(sao)* Connection health check command and Filament action
- *(sao)* Graylog logs driver
- *(sao)* Eight more logs drivers (error trackers + log aggregators)
- *(sao)* Three more issues drivers (youtrack, azure devops, linear)
- *(sao)* Inbound webhook transport for logs connections
- *(sao)* Scheduled inbound issue polling (pull transport)
- *(sao)* Read-only Filament surface for ingest events
- *(sao)* Auto-open tickets from error signals
- *(sao)* Schedule the connection health probe
- *(sao)* Sao:ingest:replay command to dry-run a stored event
- *(sao)* Manual accept action for ownership suggestions
- *(sao)* Writable Filament CRUD for source profiles
- *(sao)* Expose operational actions as HTTP domain actions
- *(sao)* Add tickets `transitions` read action for guided kanban drops
- *(sao)* Model ticket↔label and ticket↔watcher pivots explicitly
- *(sao)* Add release-health read-model (#2)
- *(sao)* Deploy & rollout ingest core (#1)
- *(sao)* Attribution core — Fixes/Mentions change refs from code text (spec #1 phase 1)
- *(sao)* Complete fix-attribution pipeline — release attr, pull scan, PR webhook, closure (spec #1 phases 2-5)
- *(sao)* Deterministic release promotion — ReleaseRegistrar + ReleaseSyncService (spec #1)
- *(sao)* External tracker migration importer + cutover (spec #2)
- *(sao)* Data retention prune (sao:prune) — keep the store bounded
- *(sao)* Resumable tracker import + comment/attachment history
- *(import)* Register a sao.ticket entity for the generic bulk import

### 🚜 Refactor

- *(sao)* Capability calls receive a BindingContext (phase 3b task 2)

### 📚 Documentation

- *(sao)* Record what slice 1a delivered
- *(sao)* Add developer "how it works" section to module RAG doc
- *(sao)* Document phase 1b ticket enrichment
- *(sao)* Document the external issues driver roster
- *(sao)* Document vcs/releases on the git-host drivers
- *(sao)* Document phase 2 signals and loop protection
- *(sao)* Note the signal filament surface
- *(sao)* Document phase 4 ingest and source profiles
- *(sao)* Document phase 5b code-to-work, releases and deploy census
- *(sao)* Document phase 6 fix propagation and evidence-based closure
- *(sao)* Document the gitea and sentry drivers
- *(sao)* Add plan for release health & deploy ingest
- *(sao)* RAG glossary updates for deploy/release-health/attribution/closure; clarify release-version normalization

### 🧪 Testing

- *(sao)* Per-capability conformance suite with in-memory driver (phase 3a task 6)
- *(sao)* Add dev seeder for the SAO SPA with demo data, a role and a user

### ⚙️ Miscellaneous Tasks

- *(models)* Remove unnecessary comments from model properties

## [0.2.0] - 2026-08-04

### 🚀 Features

- *(sao)* Seed domain permissions and gate the workflow override
- *(sao)* Add Filament resources for the configuration entities

### ⚙️ Miscellaneous Tasks

- *(models)* Add IdeHelper mixins to project and ticket models

## [0.1.0] - 2026-08-03

### 🚀 Features

- *(sao)* Add the table registry and domain enums
- *(sao)* Add the project entity with its ticket counter
- *(sao)* Add global ticket statuses with canonical categories
- *(sao)* Add shareable workflow schemes and their transitions
- *(sao)* Add ticket types with per-project association
- *(sao)* Add tickets with a row-locked per-project key allocator
- *(sao)* Enforce workflow transitions in the domain service
- *(sao)* Add ticket comments with human and system origins
- *(sao)* Build the ticket timeline from comments and versions

### 🐛 Bug Fixes

- *(sao)* Put the column comment before constrained(), not after
- *(sao)* Respect model connection affinity in the key allocator

### 🚜 Refactor

- *(sao)* Remove dead scaffolding and enforce the Core dependency
- *(sao)* Use Core HasActivation instead of hand-rolled activation

### 📚 Documentation

- *(sao)* Add licence, readme, changelog and repository hygiene
- *(sao)* Add the module glossary and RAG corpus

### 🧪 Testing

- *(sao)* Add module test harness and registration coverage

### ⚙️ Miscellaneous Tasks

- *(sao)* Declare module identity, licence and Core dependency
- *(sao)* Align quality tooling with the sibling modules
- *(sao)* Add release scripts and module agent rules

<!-- generated by git-cliff -->
