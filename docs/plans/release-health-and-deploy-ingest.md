# Plan — Release Health & Deploy Ingest

**Status:** proposed · **Module:** SAO · **Method:** TDD (Pest feature tests)
**Builds on:** phase 5b (releases / deploy census) and phase 6 (fix propagation & evidence-based closure).

Distilled from the talk *“The Self-Healing Canary: Integrating Agentic AIOps into
Your Releases”* (K. Dubois). SAO takes the **post-hoc, correlation half** of that
idea; the canary control plane stays external.

## Boundary — what stays out of SAO

The canary mechanics — traffic weighting, the real-time **promote / rollback
gate**, the Argo metric plugin — belong to the deployment platform (Argo
Rollouts / Flagger / k8s). SAO is never in the request path and does not gate a
rollout. It **records what happened and correlates it** to code, signals and work.

---

## #1 — Deploy & rollout ingest

### Goal
Today SAO knows only a snapshot (`Environment.current_version`). Record a durable,
idempotent history of deploy events per *(project, environment, release)* — the
missing link between a release and the signals that follow it, and the precise
time anchor #2 needs.

### Reuses
- `Environment` (`project_id, name, current_version, last_seen_at`), `Release`
  (`version, status, released_at`).
- `IngestEvent` — the raw-delivery audit trail (dedupe by delivery id, explicit
  `outcome`).
- `DriverWebhookIngestService` + `POST api/v1/webhooks/{connection}` — inbound
  transport pattern (driver verifies its own signature, `unpack()`s the body).
- `Capability` enum + the open `DriverRegistry` — a new capability slots in
  without editing SAO core.

### New — `Deployment` model (`sao_deployments`)
| Field | Type | Meaning |
|---|---|---|
| `project_id` | FK | Owning project. |
| `environment_id` | FK, nullable | Target environment (`production`, `canary`, …). |
| `release_id` | FK, nullable | Resolved release; nullable when only a version string is known. |
| `version` | string | Deployed version as reported (authoritative even without `release_id`). |
| `status` | `DeploymentStatus` | Lifecycle (below). |
| `connection_id` | FK, nullable | Source connection that reported it. |
| `external_id` | string, nullable | Source id / delivery id — idempotency key. |
| `started_at` | datetime | When the deploy/rollout began. |
| `finished_at` | datetime, nullable | Terminal timestamp — window anchor for #2. |
| `meta` | json, nullable | Driver-specific extras (canary weight, sha, actor). |

Unique on `(connection_id, external_id)`. Model + factory + timestamps + prefixed
table per module conventions.

`enum DeploymentStatus`: `started`, `succeeded`, `failed`, `rolled_back`, `superseded`.

### Transport (recommended)
- A new **`deploy` capability** contract (`DeployCapability::unpack()` → normalized
  `DeployEvent`s), mirroring how `logs` drivers unpack into events, with a
  `DeployConformance` battery over `Http::fake()`.
- First drivers: a generic `WebhookDeployDriver` (token/HMAC-verified JSON) plus
  one concrete (GitHub Deployments or Argo Rollouts notifications).
- `DeploymentIngestService` — the `logs` counterpart to `SignalIngestService`:
  dedupe by `(connection, external_id)`, upsert the `Deployment`, and on terminal
  `succeeded` advance `Environment.current_version` so the census becomes a
  **projection** of the history, never hand-set. Every delivery audited (forged →
  401, stored rejected).
- `sao:deploy:record {--env=} {--version=} {--status=}` command — integration-free
  recording/replay for tests and CLI-only CD steps.

### Correlation hook (minimal here)
Dispatch `DeploymentRecorded` on persist. That is the seam #2 and future
automation subscribe to (e.g. a `rolled_back` deploy can annotate the release’s
tickets). #1 does not open tickets itself.

### Surfaces & tests
- Read-only Filament `DeploymentResource`; SAO SPA “Deployments” list + deploy
  markers on release/environment views.
- TDD: idempotent re-delivery; census projection on `succeeded`; full status
  lifecycle; forged/unknown-connection delivery audited-and-rejected; conformance
  over `Http::fake()`.

### Out of scope
Traffic weighting and the promote/abort decision — Argo/Flagger.

---

## #2 — Release-health & regression read-model

### Goal
Answer, for any release: *did this release make things worse?* SAO already answers
the sibling “is the fix deployed everywhere?” via `FixStatusResolver` /
`TimeToTruthService`. #2 adds the other direction — **did a release introduce new
or worsening signals** — as a pure read model, no rollout control.

### Reuses
- `SignalOccurrence` already carries `environment` + `occurred_at` — the axes for
  windowed, per-environment counting. `Signal` carries `group_key, first_seen_at,
  occurrence_count, state`.
- `Release` / `Environment` for subject and deploy time; `Deployment.finished_at`
  (#1) as the precise window anchor.
- `TicketRelease` to attribute signals whose tickets shipped in the release.
- `ClosurePolicy`-style thresholds; the optional AI seam (`AiTextGenerationRequested`);
  the signal→ticket auto-opener.

### Core service (pure read model)
```php
final class ReleaseHealthService
{
    public function forRelease(
        Release $release,
        ?Environment $environment = null,   // scope to one env, or all
        ?CarbonInterval $window = null,     // default: anchor → now, capped
    ): ReleaseHealth;
}

final readonly class ReleaseHealth
{
    Verdict  $verdict;             // Healthy | Degraded | Regressed | Unknown
    Carbon   $windowStart;        // Deployment.finished_at ?? Release.released_at
    Carbon   $windowEnd;
    ?Release $baseline;           // previous release, equivalent window
    array    $newSignals;         // group_keys first seen inside the window
    array    $regressedSignals;   // existing group_keys whose rate rose vs baseline
    int      $totalOccurrences;
    ?float   $occurrenceDeltaPct; // vs baseline window (null when no baseline)
    array    $contributing;       // signals behind the verdict, ranked
}
```

### Deterministic definitions
| Concept | Rule |
|---|---|
| window | From `Deployment.finished_at` (#1) when present, else `Release.released_at`; end = now, capped by `config('sao.release_health.window')`. |
| attributed signals | Occurrences in the window, scoped to the release’s project (and `environment` when given), plus signals on tickets attributed to the release via `TicketRelease`. |
| new | A `Signal` whose `first_seen_at` falls inside the window. |
| regressed | An existing `group_key` whose occurrence **rate** in the window exceeds its baseline-window rate by a configured factor. |
| baseline | The previous release by `released_at` / version, over an equal-length window after *its* deploy — like-for-like. |

### Verdict (thresholds in config, `ClosurePolicy`-like)
- **Healthy** — no new signals, rate flat or down.
- **Degraded** — new signals below the regression bar.
- **Regressed** — new/worsening past the threshold.
- **Unknown** — no data or no baseline yet (never a false green).

### Optional layers (each independently shippable)
- `ReleaseHealthSnapshot` — persisted verdict-at-a-time (like `ClosureAudit`) for
  trend/history.
- Auto-raise on `Regressed` — reuse the signal→ticket auto-opener (advisory).
- AI-phrased verdict — through the existing optional AI seam; deterministic text
  fallback with no listener.
- Surfaces — health badge per release in the SPA with drill-down; Filament widget.

### Tests · TDD
- Pure service fed injected signals/occurrences/releases — verdict boundaries.
- Regression detection vs a baseline release; equal-window like-for-like.
- No baseline / no occurrences → `Unknown`.
- Per-environment scoping; window anchored on a `Deployment` vs on `released_at`.

### Out of scope
Being in the deploy path or making a promote/rollback call.

---

## Sequencing
#2 works on its own with `Release.released_at` as the anchor, so it ships first
(pure, self-contained, no new transport). #1 then sharpens it: a real
`Deployment.finished_at` gives an exact anchor and unlocks per-deploy (canary vs
stable) health and rollback correlation. Both are additive to phases 5b/6 and
change nothing in the deployment platform.

**Recommended order:** ship **#2** against release time first, then **#1** to feed
it precise deploy anchors and history.
