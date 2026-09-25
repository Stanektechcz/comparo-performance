# Programmatic SEO

## Supported combinations

Product × Country · Brand × Country · Category × Country · Shop × Country ·
Ingredient × Category · Brand × Category · Product × Product · Shop × Shop.

The console shows, per combination, the number of possible URLs, how many pass the quality
threshold, and the pass rate. Nothing is generated blindly: at current thresholds most
Product × Product pairs fail, which is the correct outcome.

## Quality gate

A combination page may be indexable only when it has genuinely unique data:

```
offers        >= threshold.offers   (default 2)
distinct shops >= threshold.shops   (default 2)
reviews       >= threshold.reviews  (default 3)
price history exists for both entities
unique explanatory content (context copy, market data, or a computed insight)
```

Thresholds are editable in the console; raising `offers` from 2 to 4 visibly collapses the
indexable count, which is the point — the control is real.

## Page quality score (0–100)

Unique data, offer count, review count, price history depth, content uniqueness, entity
relationships, internal links, freshness and a search-demand proxy. Displayed as
**Indexable / Borderline / Noindex**.

## Programmatic content safety

Forbidden by construction: pages that differ only by a substituted country or brand name,
mass-generated thin variants, doorway pages, fake authorship, fabricated statistics, machine-written
reviews. Every market page derives its body from market-specific data — which shops deliver,
what shipping costs, local price levels, market-specific reviews and compliance status.
If a market has fewer than three delivering shops, the page is noindex rather than padded.

## Comparison pages

`/compare/{a}-vs-{b}` renders the full metric table (best total, product price, shipping, price
per serving, cheapest shop, pack size, servings, rating, reviews, shops offering, availability,
delivery estimate, key ingredients, compliance status) with a shareable canonical URL. Shop × Shop
comparisons follow the same gate: trust, reviews, pricing position, shipping, markets, catalogue
size and active deals, indexed only where both shops have enough data.

## Compliance beats opportunity

No programmatic page is created to harvest searches for restricted products. A product that is
`not_allowed` or `prescription_only` in a market is excluded from that market's programmatic
pages entirely, and `unknown` status blocks commercial recommendation until reviewed.
