# Backlog (phase order)

Priority rule: (1) critical security regression, (2) broken test/build, (3) active vertical-slice
dependency, (4) `docs/implementation-status.md` gaps, (5) roadmap order below, (6) feature matrix,
(7) prototype-parity gaps, (8) documented architecture, (9) lower-priority improvements.
Never jump between unrelated modules. Task-level detail for the active phase lives in
[TASK-GRAPH.md](TASK-GRAPH.md).

Phase numbering follows the autonomous-orchestrator programme (2026-09-25). The earlier
`docs/architecture/migration-roadmap.md` used a coarser numbering; the mapping is in the last column.

| Phase | Objective | Status | Exit criterion (working vertical flow) | Old roadmap |
|---|---|---|---|---|
| 0 | Foundation | DONE | baseline commit `fe8c42b` | 0 |
| 1 | Catalogue, offers, landed price, compliance, ranking, history (first slice) | DONE (first slice) | product page from DB with parity | 1 |
| **2** | **Merchant feeds + canonical matching** | **IN PROGRESS** | feed URL/upload → parse → match → offer update → product page | 2 |
| 3 | Search & discovery (Scout/Meilisearch, facets, suggest, zero-result analytics) | NOT STARTED | real indexed search obeying market + compliance | 3 |
| 4 | Reviews, orders, purchase verification | NOT STARTED | review + proof → verification → moderation → aggregate → merchant notification | 4 |
| 5 | Affiliate system (`/go`, attribution, conversions, reconciliation) | NOT STARTED | all purchase CTAs use `/go`; durable attribution | 5 |
| 6 | Account, alerts, notifications, email | NOT STARTED | price change → alert → in-app + queued email | 6 |
| 7 | Complete merchant platform | NOT STARTED | onboarding, team, real dashboard, tenant security gate | 7 |
| 8 | Complete staff platform (moderation, ops, audit, feature flags, support) | NOT STARTED | audited privileged actions from the console | 8 |
| 9 | Community & reputation | NOT STARTED | ledger-based reputation with moderation | 9 |
| 10 | Live rooms & governance | NOT STARTED | transport-abstracted rooms, juries, wiki | 9 |
| 11 | Content, research & SEO | NOT STARTED | sitemaps, hreflang, RSS, llms.txt, editorial workflow | 12 |
| 12 | Growth OS (real derived signals) | NOT STARTED | evidence-backed opportunities | 11 |
| 13 | Commercial OS (plans, entitlements, billing, sponsored inventory) | NOT STARTED | plan → entitlement → invoice → account UI | 10 |
| 14 | Public API & integrations hardening | NOT STARTED | hashed scoped keys, plan-aware limits, signed webhooks, OpenAPI | 10 |
| 15 | Privacy & GDPR | NOT STARTED | async export, deletion/anonymisation, retention | 13 |
| 16 | Performance & scale | NOT STARTED | reviewed query plans, cache, queues, bundles | 13 |
| 17 | Accessibility & UX hardening | NOT STARTED | keyboard-only major flows at all widths | 13 |
| 18 | Observability & operations | NOT STARTED | structured logs, health, runbooks, backup/restore | 13 |
| 19 | Production release gate | NOT STARTED | all gates in QUALITY-GATES.md level D | 13 |

## Cross-cutting items pulled forward

| Item | Where | Why early |
|---|---|---|
| AuditLogger service | Phase 2 (P2-02) | manual match decisions and feed credential changes are privileged actions |
| Feature flags | Phase 2 (P2-03) | staged rollout of feed scheduling and auto-publishing |
| Domain events + after-commit listeners | Phase 2 | `PriceChanged` replaces model-hook cache invalidation incrementally |
| Direct merchant purchase links | Phase 5 | known gap: product pages still link straight to merchant URLs |
| GDPR export/erase | ≤ Phase 15 | must not slip further |

## Follow-ups discovered during Phase 2

| ID | Severity | Finding | Planned |
|---|---|---|---|
| F-01 | MAJOR | `merchant_trust_signals` is append-only only at the model level; `merchant_id … cascadeOnDelete` silently deletes trust history when a merchant row is deleted | Phase 7/8: DB triggers + restrict FK (merchants are suspended, never deleted) |
| F-02 | MINOR | `AssignCorrelationId` runs before TrustProxies; behind a proxy it would record the proxy IP | Phase 18 (trusted proxy setup) |
| F-03 | MINOR | Audit system actor component stored inside `after._actor_component` | when the audit viewer is built (Phase 8) |
| F-04 | MINOR | tools/prototype-parity/export-fixtures.mjs is 893 lines (> 800 soft ceiling) | split per-engine exporter modules when next touched |
