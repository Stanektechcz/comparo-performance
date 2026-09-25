# Merchant feeds

## Ways a merchant can publish products

| Method | Use case | Cadence |
| --- | --- | --- |
| XML feed URL | most shop platforms | 1–24 h, per plan |
| CSV / TSV | exports, spreadsheets | 1–24 h |
| JSON feed URL | headless shops | 1–24 h |
| REST API push | real-time price and stock | on change |
| Manual entry | long tail, single SKUs | ad hoc |

## Field contract

Required: `merchant_sku`, `product_name`, `price`, `currency`, `availability`, `product_url`.
Strongly recommended: `ean`/`gtin`, `brand`, `stock`, `affiliate_url`, `image_url`,
`category`, `variant`, `pack_size`, `shipping_cost`, `old_price`.

```xml
<SHOPITEM>
  <MERCHANT_SKU>PEA-186</MERCHANT_SKU>
  <PRODUCTNAME>Whey Isolate 90 - Vanilla 900g</PRODUCTNAME>
  <BRAND>IRONFORGE</BRAND>
  <EAN>8591047514</EAN>
  <PRICE_VAT>40.54</PRICE_VAT>
  <CURRENCY>EUR</CURRENCY>
  <AVAILABILITY>in_stock</AVAILABILITY>
  <STOCK>172</STOCK>
  <URL>https://peaksupps.de/p/whey-isolate-90</URL>
  <IMGURL>https://peaksupps.de/img/whey-isolate-90.jpg</IMGURL>
  <CATEGORY>Protein | Isolate</CATEGORY>
  <VARIANT>Vanilla</VARIANT>
  <PACK_SIZE>900 g</PACK_SIZE>
</SHOPITEM>
```

```csv
merchant_sku;product_name;brand;ean;price;currency;availability;stock;url
PEA-186;Whey Isolate 90 Vanilla 900g;IRONFORGE;8591047514;40.54;EUR;in_stock;172;https://…
```

Availability is normalised to `in_stock | low_stock | preorder | out_of_stock`; common synonyms
(`available`, `na dotaz`, `24h`, `backorder`) are mapped by a per-merchant dictionary.

## Import pipeline

```
download → checksum → parse → normalise → validate → match → diff → commit → index → invalidate
```

- **checksum** — identical payload since last run ends the job early.
- **normalise** — currency, units (g/kg, caps/tabs), decimal separators, HTML stripped from titles.
- **validate** — rows failing the required-field contract are rejected with a line number; a run
  with more than 20 % rejected rows is aborted and reported instead of committed.
- **diff** — price/stock changes update `price`; a new `price_history` row is written on any
  material change and once per day regardless.
- **commit** — transactional per merchant; a failed run leaves the previous state intact.

Runs are recorded with counts (items, matched, unmatched, rejected, errors), duration and a link
to the archived payload in S3 for 30 days.

## Matching engine

Merchant item → canonical `product`. Cascade, first hit wins:

1. **GTIN/EAN exact** on `product.gtin` or `product_variant.gtin` → `auto`, confidence 0.98.
2. **Previous decision** for the same `(merchant_id, merchant_sku)` → reuse, confidence 1.0.
3. **Brand + normalised name + pack size** exact → `auto`, confidence ≥ 0.94.
4. **Fuzzy**: token-set similarity on brand + name with a Levenshtein fallback per token, plus
   pack-size agreement. ≥ 0.90 → `auto`; 0.60–0.90 → `suggested` (human confirms);
   < 0.60 → `unmatched`.
5. **Compliance hold** — a matched product whose status is `not_allowed` or
   `prescription_only` for one of the merchant's selling markets is stored but not published
   in that market, flagged `compliance_hold`.

Reviewer actions: **confirm match**, **choose a different product**, **create new canonical
product** (goes to the admin catalogue queue as `draft`), **skip**. Every decision writes an
audit entry and trains the per-merchant title dictionary, so recurring naming quirks
("NIGH casein" → "Casein Night Protein") stop reappearing.

## De-duplication of canonical products

New products created from feeds are checked against existing ones on GTIN, brand + name distance
and pack size. Duplicates are merged with `product.merged_into_id`; offers, reviews and price
history follow the surviving row, and the old slug 301-redirects.

## Quality signals shown to the merchant

Match rate, unmatched count, rejected rows, price divergence versus the landing page (sampled
crawl), stale-item ratio (items unchanged for more than 30 days), and error log. Verified status
requires a match rate above 85 % and a landing-page price divergence under 3 %.

## Prototype behaviour

The Feed & matching tab accepts a pasted XML, CSV or JSON sample, runs the real cascade
(`parseFeed` → `matchItem`: EAN first, then fuzzy title scoring against the canonical
catalogue) and drops the results into the matching queue with live confidence values, where
Match / New product / Skip behave as they would in production.
