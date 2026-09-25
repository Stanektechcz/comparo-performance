# Knowledge graph

## Entities and IDs

Every important record has a stable, human-readable internal ID alongside its slug and database id:

```
PRD-00001  product            BRD-00001  brand
SHP-00001  merchant           CAT-00001  category
ING-00001  ingredient         MKT-00001  market / country
OFR-00001  offer
```

Production keeps these as external identifiers next to a UUID primary key, GTIN/EAN, and merchant
SKU, so the canonical entity survives slug changes, merges and feed churn.

## Relationships

```
Product ─┬─ Brand ─── Country (origin)
         ├─ Category ─── related categories
         ├─ Ingredient(s) ─── Category, Brand
         ├─ Offer(s) ─── Merchant ─── Market(s) ─── ShippingOption
         ├─ Review(s) ─── User ─── Reputation
         ├─ Deal(s) / Coupon(s)
         ├─ PriceHistory
         ├─ ComplianceStatus (per market)
         └─ ForumTopic(s), Guide(s), Comparison(s)
```

The console's **Entity graph** renders this for any product: brand, category, ingredients, shops,
markets, review count, discussions and the indexable pages the entity owns — every node clickable.

## Entity resolution

Cascade, first hit wins: GTIN/EAN exact → previous decision for the same
(merchant, SKU) → brand + normalised name + pack size → fuzzy title similarity with pack-size
agreement and ingredient overlap. Confidence is surfaced as **99 % exact / 92 % high /
78 % possible / unknown**, and anything below high goes to a human.

## Duplicate detection and merging

**Duplicate candidates** lists pairs with similarity, EAN agreement, brand agreement, pack
differences and a note. Merging is implemented: offers, reviews, price history, saved items and
affiliate links transfer to the surviving canonical entity, a 301 is written into the redirect
manager, and an audit entry records the counts moved.

## Retrieval metadata (RAG-ready)

Every retrievable document carries `entity_type`, `entity_id`, `market`, `language`,
`updated_at`, `visibility` and `compliance`, so a retrieval layer can filter by market and
legality before an answer is composed. Candidate document types: products, shops, offers with
price history, reviews (aggregated), guides, forum answers, market datasets and methodology pages.

## Future hybrid retrieval

Keyword (Meilisearch/Typesense) + fuzzy + entity match + filters today; vector embeddings added as
a parallel index with reciprocal-rank fusion, never as a replacement for exact GTIN and entity
matching. The public UI already exposes the intended control: **Relevant** vs **Exact**.
