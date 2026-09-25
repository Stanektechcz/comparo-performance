# Compliance engine

Food supplements are regulated nationally. The same product can be a normal supplement in one
market, restricted in another, a medicinal product in a third and prohibited in a fourth. The
platform therefore treats market legality as first-class data that **removes** offers from the
user's view. Nothing in the system helps anyone bypass a restriction, a geoblock, an age limit or
an advertising rule.

## Model

```
ProductComplianceRule
  product_id, country_id
  status      allowed | restricted | prescription_only | not_allowed | unknown
  reason      human-readable legal basis
  source      authority, decree, EU regulation, internal review
  reviewed_by, reviewed_at, expires_at
  unique (product_id, country_id)
```

Country-level configuration lives on `country`: `min_age`, `vat_rate`, `shipping_rules`,
`product_availability_rules` and `compliance_rules` (jsonb — e.g. caffeine per serving caps,
banned botanicals, mandatory warnings).

## Status semantics

| Status | Listed | Purchase CTA | Recommendable | Notes |
| --- | --- | --- | --- | --- |
| `allowed` | yes | yes | yes | default after review |
| `restricted` | yes, with warning | yes | **no** | excluded from homepage, deal hub, recommendations, alerts |
| `prescription_only` | informational page only | **no** | no | offers hidden, reason shown |
| `not_allowed` | informational page only | **no** | no | offers hidden, reason shown |
| `unknown` | informational page only | **no** | no | blocks until a human reviews; surfaced in the admin queue |

"Recommendable" gates every automated surface: homepage rails, category placements, deal hub,
search boosting, email digests, price and deal alerts, and comparison suggestions.

## Where the rule is applied

Server-side, before serialisation, in this order:

```
country resolved (user selection → account default → IP country hint → site locale)
   ↓
compliance status for (product, country)
   ↓
not_allowed / prescription_only / unknown  →  offers = [], purchasable = false, reason returned
restricted                                 →  offers returned + warning, recommendable = false
allowed                                    →  normal path
   ↓
shipping filter: merchants without a shipping zone for that country are dropped
   ↓
age gate: min_age of the country applied to the whole category tree
```

Because the check precedes serialisation, no client, cache or feed can resurrect a blocked offer.
Cache keys always include the country code.

## Admin workflow

The admin console shows a per-market matrix of every product with its status, reason, source and
reviewer. The dashboard highlights **UNKNOWN COMPLIANCE** as a queue with a count; a compliance
manager opens a row, sets the status, records the legal basis and saves — which writes the rule and
an `audit_log` entry (`compliance.rule.updated`, before/after diff).

New products from feeds enter as `unknown` for every market where the merchant sells. Rules can
carry `expires_at` for temporary national decisions; expiry reverts the pair to `unknown` and
re-queues it rather than silently allowing sale.

## Worked examples from the seed data

| Product | Market | Status | Basis |
| --- | --- | --- | --- |
| Melatonin Sleep 1 mg | DE, CZ, SK, AT, PL, FR, IT, ES | `prescription_only` | above the food-supplement threshold, classified as a medicinal product |
| Thermo Cut Yohimbine | DE, FR | `not_allowed` | yohimbine not permitted in food supplements |
| Thermo Cut Yohimbine | IT, ES, PL | `restricted` | permitted with mandatory warning and capped daily dose |
| Pre-Burn Extreme | FR, IT | `restricted` | caffeine above 200 mg per serving requires on-pack warning |
| Pre-Burn Extreme | SE, NL | `unknown` | imported by feed, awaiting review |

## Adjacent guardrails

- **Age gating.** Category-level minimum age per country; the age gate is a country rule, not a
  user preference, and cannot be dismissed permanently.
- **Advertising.** Health and disease claims are stripped from feed titles and blocked in editorial
  content review; restricted products are never eligible for sponsored placements.
- **Payments and shipping.** The platform never brokers payment and never proposes alternative
  routing for a product that cannot be shipped to a market legally.
- **Editorial.** Content about a product carries the same market notice as the product page.

## Testing

`compliance-filtering` suite asserts: blocked statuses return zero offers for the country;
restricted products never appear in any recommendation surface; unknown blocks purchase; the rule
matrix is enforced for every locale and cache key; and every status change lands in the audit log.
