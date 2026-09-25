# Search

## Index coverage

Products, variants, brands, shops, categories, ingredients, deals, coupons, guides, forum topics
and replies, reviews, users and identifiers (internal SKU, EAN/GTIN).

## Matching

Normalisation (lowercase, diacritics stripped) → exact identifier match (SKU, EAN) → prefix and
substring → token-set match → Levenshtein fallback per token (distance ≤ 2 for tokens longer than
5 characters) → synonym expansion. `"wey isolat"` finds Whey Isolate 90; `8591047514` jumps
straight to the entity.

## Intent classification

| Intent | Example | Ideal landing page |
| --- | --- | --- |
| NAVIGATIONAL | "peaksupps" | shop entity |
| PRODUCT | "whey isolate 90" | product entity |
| TRANSACTIONAL | "whey isolate 90 price" | product offer table |
| COMMERCIAL | "cheapest whey protein germany" | market landing page |
| COMPARISON | "product a vs product b" | comparison page |
| SHOP_DISCOVERY | "best shops germany" | country shop ranking |
| REVIEW | "ironlab store reviews" | shop review hub |
| INFORMATIONAL | "is melatonin legal in germany" | ingredient / guide |
| LOGISTICS | "shops delivering to sweden" | market hub |

The search page shows the detected intent and a "why these results" explanation; the console maps
query → intent → recommended page type, which is what drives the content-gap list.

## Natural-language filtering

`parseNL()` extracts market, maximum total, minimum rating, product, shop, brand, category,
free-shipping and in-stock constraints from a plain sentence and shows the parsed filters back to
the user. "shops delivering to Germany under €50 rated above 4" becomes four filters, visible and
editable.

## Zero-result recovery

No dead end: did-you-mean suggestions, similar products, related brands and categories, and
relevant community threads. Every zero-result query is logged with its intent and appears in the
console the same session, feeding the content-gap engine.

## Synonyms

Locale-aware synonym sets (`shop/store/merchant/eshop`, `gainer/mass gain/bulking`,
`creatine/kreatin/creapure`, `shipping/versand/doprava/livraison`) are listed in the console and
applied at query time.

## Analytics

Top queries, demand proxy, CTR, shop clicks, saves, zero-result volume and trend, plus this
session's live search log. These metrics are what the growth surfaces consume — see
[ANALYTICS.md](ANALYTICS.md).
