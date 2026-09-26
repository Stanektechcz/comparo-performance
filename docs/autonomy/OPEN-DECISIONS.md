# Open decisions (autonomy log)

Business decisions D-01…D-29 (launch markets, VAT, affiliate networks, retention, …) live in
[`docs/architecture/open-decisions.md`](../architecture/open-decisions.md) with their safe defaults.
This file records decisions the orchestrator took autonomously under the safe-default policy
(least destructive, no external side effects, configurable, data-preserving, documented).
Revisit any of them by changing configuration or writing an ADR.

**Owner delegation (2026-09-26).** The repository owner delegated all open points to the engineering
organisation ("rozhodni všechny body", i.e. "decide all the points"). Every A-xx below has been
reviewed and is **CONFIRMED** or **AMENDED** (the Status column holds the new wording). The business
decisions D-01…D-29 are now decided in `open-decisions.md`, consolidated in
[ADR-0018](../adr/0018-business-decisions-baseline.md). The delegation covers engineering defaults
only. Legal, tax and DPO sign-off, contracts, accounts and credentials remain human actions and are
listed in ADR-0018.

| ID | Date | Decision taken | Safe default / reversibility | Owner to confirm | Status (2026-09-26) |
|---|---|---|---|---|---|
| A-01 | 2026-09-25 | Adopt the orchestrator's phase numbering (2 feeds … 19 release) | mapping kept in BACKLOG.md; roadmap doc left as history | CTO | CONFIRMED |
| A-02 | 2026-09-25 | No git worktrees for code-writing agents yet; disjoint file ownership on a phase branch instead | worktrees lack `vendor/` + `node_modules/`; revisit when CI caches exist | CTO | CONFIRMED |
| A-03 | 2026-09-25 | Commits carry the `Co-Authored-By: Claude` trailer; phase work on `phase-N/*` branches, fast-forwarded to `main` after Gate C | plain git history, no rewrites | repository owner | CONFIRMED |
| A-04 | 2026-09-25 | `CLAUDE.md`, `boost.json`, `.claude/agents/`, `.claude/launch.json` are versioned; `AGENTS.md`, `.mcp.json`, `.claude/skills` stay generated/ignored | `.gitignore` edit only | repository owner | CONFIRMED |
| A-05 | 2026-09-25 | Feed context namespace is `App\Domain\Feeds` (target architecture name), not `MerchantFeeds` | naming only | CTO | CONFIRMED |
| A-06 | 2026-09-25 | Matching engine = intel.js Engine 2.0 (C-23); HTML `matchItem` not ported; `constructor` token quirk not replicated | ADR 0012; parity fixtures prove the rest | Head of Data | CONFIRMED |
| A-07 | 2026-09-25 | Invalid/short GTINs are a row **warning** (`INVALID_GTIN`); the raw EAN is still used for exact matching | seed EANs are 10–11 digits; flip to reject via config later | Head of Data | CONFIRMED |
| A-08 | 2026-09-25 | Unchanged prices write at most one `scheduled` snapshot per offer per UTC day; same-day re-runs write nothing | reconciles MERCHANT-FEEDS "daily regardless" with idempotency | Head of Data | CONFIRMED |
| A-09 | 2026-09-25 | Runs with > 20 % rejected rows fail with `REJECT_THRESHOLD_EXCEEDED` and publish nothing | config `comparo.feeds.max_rejected_ratio` | Merchant Ops | CONFIRMED |
| A-10 | 2026-09-25 | Missing SKUs deactivate after 2 consecutive published runs or 7 days unseen; runs that would deactivate > 50 % of listings hold deactivation | D-25 defaults, config values | Head of Data | CONFIRMED (= D-25 decision) |
| A-11 | 2026-09-25 | Unchanged-checksum runs refresh `last_seen_at` / `source_updated_at` | config `comparo.feeds.unchanged_refreshes_freshness` | Head of Data | CONFIRMED |
| A-12 | 2026-09-25 | `URL_DOMAIN_MISMATCH` is a warning only; destination enforcement arrives with `/go` (Phase 5) | no data loss | Head of Partnerships | AMENDED: `URL_DOMAIN_MISMATCH` stays a row warning at ingest. From Phase 5, `/go` refuses the outbound hop when the destination host is not a verified merchant domain, and the offer then shows no purchase link |
| A-13 | 2026-09-25 | Feed-level shipping is stored raw; landed shipping stays merchant-zone based | D-07 | Merchant Ops | CONFIRMED |
| A-14 | 2026-09-25 | One owning feed source per merchant SKU; a second source sending the same SKU gets `SKU_OWNED_BY_OTHER_SOURCE` | no silent overwrites | Merchant Ops | CONFIRMED |
| A-15 | 2026-09-25 | Creating canonical products from candidates is a Phase 8 staff catalogue action; Phase 2 staff can link a candidate to an existing product or reject it | no auto-created products | Head of Catalogue | CONFIRMED |
| A-16 | 2026-09-25 | Staff console prefix `/admin` (Horizon stays at `/staff/horizon`) | route prefix only | CTO | CONFIRMED |
| A-17 | 2026-09-25 | Feature flags: config-backed wrapper (`App\Domain\Platform\Features`) with optional DB overrides; Pennant only after dependency approval | wrapper hides the backend | CTO | CONFIRMED. Planned flags from ADR-0018 are added as `Feature` cases defaulting to **off** |
| A-18 | 2026-09-25 | `confirm` bucket (65–89) stays unpublished until a merchant or staff decision; `auto` (≥ 90) publishes behind flag `matching-auto-publish` | flag can disable auto-publishing | Head of Data | CONFIRMED |
| A-19 | 2026-09-25 | Matches to products blocked in the feed market become `compliance_hold` (unpublished, staff queue) | least exposure | Compliance Lead | CONFIRMED (consistent with D-11) |
| A-20 | 2026-09-25 | Search lives at `/search` (+ `/api/public/v1/search/suggest`) with the existing market context; locale/market URL prefixes come for all public routes together in Phase 11; search pages are `noindex,follow` | route rename later with 301s | Head of SEO | CONFIRMED |
| A-21 | 2026-09-25 | Products blocked in the visitor's market are excluded from search results and suggestions; `unknown` products are listed without purchase data | least exposure; configurable later | Compliance Lead | CONFIRMED (consistent with ADR-0007 / D-11) |
| A-22 | 2026-09-25 | One `products` index with a per-market map for active markets only (not 27 indexes) | full reindex on country activation | CTO | CONFIRMED. Launch markets are DE and AT (D-01) |
| A-23 | 2026-09-25 | The local/test search engine reproduces the prototype relevance with parity fixtures; Meilisearch (production) is held to behavioural contract tests, not prototype ordering | engine selected by `scout.driver` | Head of Data | CONFIRMED |
| A-24 | 2026-09-25 | Search analytics store no IP or user id; normalised + redacted queries, daily-rotating session hash nulled after 90 days, raw rows 13 months, demand shown only for ≥ 3 sessions | D-09 defaults, config values | DPO | CONFIRMED — DPO verification before production (D-09/D-23). F-16 (daily-session k-threshold) must be fixed to distinct sessions or accepted by the DPO |
| A-25 | 2026-09-25 | Coupons and articles are not searchable in Phase 3 (prototype coupon hits include expired / other-market coupons; articles arrive with Content in Phase 11) | additive later | Head of Product | CONFIRMED |
| A-26 | 2026-09-25 | The parity scorer keeps the prototype's substring synonym expansion (e.g. "pumpkin" → pre-workout group) because it is the spec for the local engine; Meilisearch uses token synonyms | documented deviation between engines | Head of Data | CONFIRMED |
| A-27 | 2026-09-25 | `parseNL` intent labels and the Ask feature are deferred (the prototype's country-code matching has false positives such as "it"/"at") | no UI claims | Head of Product | CONFIRMED |
| A-28 | 2026-09-25 | Shop (merchant) results in a market include only merchants with an active shipping zone for that market; did-you-mean runs on the visible entries when the final result count is zero | configurable later | Head of Product | CONFIRMED |
| A-29 | 2026-09-25 | Offers whose currency has no known exchange rate in a mixed-currency market leave the market baseline and get neither a price nor a shipping ranking advantage | conservative; revisit with the ECB rate import (D-06) | Head of Data | CONFIRMED. Together with the ECB import (D-06), rates older than `comparo.fx.max_rate_age_days` (14) also count as unknown |
| A-30 | 2026-09-26 | A single report never unpublishes a review: reports queue for moderation; a review is flagged (hidden pending moderation) only after ≥ 3 distinct reporters | `comparo.reviews.report_flag_threshold` | Head of Trust & Safety | DECIDED (owner delegation) |
| A-31 | 2026-09-26 | `AggregateRating` JSON-LD and "rated" summaries only for real (aggregated) ratings with ≥ 5 approved reviews; below that the UI says "Limited data (n)" | `comparo.thresholds.rating_min_reviews` | Head of SEO | DECIDED (owner delegation) |
| A-32 | 2026-09-26 | A merchant/product with fewer than the minimum real reviews gets a neutral "not measured" rating input (ranking fallback 4.0), not zero; review-derived trust inputs stay off until the methodology owner signs (D-10) | `comparo.trust.review_inputs` = false | Methodology owner | DECIDED (owner delegation) |
| A-33 | 2026-09-26 | Only approved reviews are eligible for aggregates; approved Suspicious reviews keep weight 0.25 (parity) | parity-preserving | Head of Trust & Safety | DECIDED (owner delegation) |
| A-34 | 2026-09-26 | Public reviewer display name is first name + last initial | presentation only | DPO | DECIDED (owner delegation) |
| A-35 | 2026-09-26 | Receipts are never public, stored on a private disk, deleted right after the decision (≤ 30 days); no EXIF stripping library in Phase 4 | no new dependency | DPO | DECIDED (owner delegation) |
| A-36 | 2026-09-26 | Feature `reviews-submission` defaults on in local/testing/demo and off in production until moderation staffing exists (D-08) | env-driven flag | Head of Trust & Safety | DECIDED (owner delegation) |
| A-37 | 2026-09-26 | Imported demo ratings are never blended with real reviews; the first eligible real approved review switches the projection to real reviews only; demo data is always labelled | no blend (D-19) | Head of Product | DECIDED (owner delegation) |
| A-38 | 2026-09-26 | Verification methods: receipt (moderated), known_order, affiliate_click_match (Null ledger until Phase 5), forwarded_email (flag off, D-27); self-declared "verified purchase" is not ported | flags + adapters | Head of Trust & Safety | DECIDED (owner delegation) |
| A-39 | 2026-09-26 | `staging` is production-like: Meilisearch required (local search drivers refused as in production), Horizon runs every supervisor (config/horizon.php `staging`), production password policy, APP_DEBUG off; unlike production it may carry the labelled prototype demo dataset (opt-in COMPARO_DEMO_ACCOUNTS) and stays resettable (no destructive-command ban) | env config | CTO | DECIDED (owner delegation) |
