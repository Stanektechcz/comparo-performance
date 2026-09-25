# ADR-0007: Compliance before serialization

- Status: Accepted
- Date: 2026-09-25
- Related: C-05, D-11, `COMPLIANCE.md`, ADR-0010, [modules/compliance.md](../modules/compliance.md)

## Context

Some products cannot lawfully be sold in some markets. The prototype (`comp()` in the HTML) treats a
missing rule as `allowed` ("Default policy") and lists `unknown` products with purchasable offers and a
−6 rank penalty. `COMPLIANCE.md` hides offers for `unknown`. The product owner's specification for
`unknown`: safe default, no purchase CTA, no recommendation, admin review queue.

## Decision

1. **Compliance is evaluated server-side, per (product, market), before anything is serialized** — Inertia
   props, public/merchant API responses, cache entries, search documents, sitemaps, notifications,
   alerts and the `/go` redirect.
2. **A missing rule resolves to `unknown`** (`ComplianceDecision::unreviewed()`, `hasExplicitRule = false`),
   never to `allowed`. `App\Domain\Compliance\Queries\ComplianceResolver` (`decide()`, `decideMany()`)
   implements this for product × market.
3. **Policy per status** (implemented by `App\Domain\Compliance\ComplianceStatus`):

   | Status | Offers serialized | Purchase links / CTA | Recommendable, best buy, alerts, sponsorship | Review queue | UI |
   |---|---|---|---|---|---|
   | `allowed` | yes | yes | yes | no | normal |
   | `restricted` | yes | yes | **no** | no | warning banner |
   | `unknown` | prices for information only | **no** | **no** | **yes** | "not yet reviewed" notice |
   | `prescription_only` | **no** | no | no | no | informational page |
   | `not_allowed` | **no** | no | no | no | informational page |

   Enum methods: `offersVisible()`, `isPurchasable()`, `isRecommendable()`, `requiresReview()`,
   `isBlocked()`, `label()`. `ComplianceDecision::toArray()` exposes `offers_visible`, `purchasable`,
   `recommendable`, `under_review`.
4. **Demo import keeps prototype data explicit.** The demo importer
   (`App\Domain\Platform\PrototypeImport\PrototypeSnapshotImporter`, constant `DEFAULT_RULE_SOURCE`)
   materialises the prototype's implicit default as explicit `allowed` rows in
   `product_compliance_rules` with `source = "Default policy (prototype demo import)"`. Production data
   never gets implicit rows; products created from feeds start without a rule, i.e. `unknown`.
5. Ranking still receives `complianceUnknown` / `complianceBlocked` so the −6 penalty and best-buy
   ineligibility stay in parity (ADR-0010); ranking never decides visibility.
6. Cache keys and search documents for compliance-sensitive data always include the market.
7. Rule changes (`compliance.manage`) write `audit_logs` and emit `ComplianceRuleChanged` (planned),
   which invalidates the affected product × market caches and search documents.

## Consequences

- **Intentional deviation from the prototype** for `unknown` (prototype: purchasable) and for the missing-rule
  default (prototype: `allowed`). Listed in ADR-0010. `compliance.json` fixtures are exported but are not
  a parity target for visibility.
- A test matrix status × surface (product page, offers API, search, sitemap, alerts, redirect) is
  required before Phase 1 exit.
- Enabling a market requires compliance review of its catalogue; until then its products show prices
  without purchase links.
- D-11 (who owns legal rule sources) stays open; the policy above is the safe default.

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Prototype behaviour (`unknown` purchasable) with a config switch (earlier C-05 draft) | Widens exposure by default; conflicts with the product owner's specification |
| `COMPLIANCE.md` behaviour (`unknown` shows no prices) | Hides useful price information that carries no purchase risk once CTAs are removed |
| Filter in the React layer | Data would already have left the server (props, caches, JSON) |
