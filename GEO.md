# GEO — generative engine optimisation

## Definition used here

GEO = making the site's *facts* retrievable, attributable and current. It is measured by the GEO
score, which is deliberately about structure and provenance, not phrasing.

## GEO score inputs

| Factor | Weight (deduction) |
| --- | --- |
| Machine-readable fact block missing | −16 |
| No direct answer block | −14 |
| No structured data | −14 |
| No source / methodology statement | −12 |
| Stale data (> 120 days) | −12 |
| Thin content | −10 |
| Non-unique programmatic content | −10 |
| No author / editor attribution (editorial pages) | −8 |

Shown per page in the inspector with every deduction listed. A page cannot reach a high GEO score
by writing more prose; it needs facts, sources and freshness.

## Machine-readable facts

Stored as fields, rendered for humans, ready to be served by an API:

```
lowest_price, average_price, median_price, low_30d, low_90d, all_time_low,
price_vs_90d_avg, volatility, offer_count, merchant_count, shop_count,
review_count, rating, verified_review_share, available_countries,
last_price_update, entity_id, market, compliance_status
```

Product, category, ingredient, market and research pages all expose a fact block with these keys.

## Unique data assets

Average / lowest / highest market price, 30- and 90-day change, price volatility, shop count,
country count, rating trends, deal frequency, average shipping cost, market price indices and
brand price positioning — all computed from Comparo's own dataset and published with method and
period at `/research`, each with a CSV download and a shareable URL.

## Claim provenance

Every assertion states where it came from: price → named merchant feed with update time;
rating → count of published community reviews; shipping → merchant feed; compliance → named
authority or internal review with reviewer and date. Internal source confidence
(verified merchant feed / manual merchant entry / community report / imported legacy value) is
visible to admins.

## Insight generation

Insights are deterministic and only emitted when the dataset supports them, e.g. "the protein
index sits at 97.4 (90 days ago = 100.0)". No sentence is generated that the data cannot back, and
nothing is presented as AI-generated when it is arithmetic.

## Compliance-aware answers

Search results, recommendations, programmatic pages and Ask Comparo all respect market status:
`allowed` and `restricted` behave differently, `prescription_only` and `not_allowed` remove
purchase paths, and `unknown` yields "market availability not verified" — never an implied legal
status.
