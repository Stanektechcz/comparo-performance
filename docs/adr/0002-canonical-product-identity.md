# ADR-0002: Canonical product identity

- Status: Accepted
- Date: 2026-09-25
- Related: C-23, ADR-0003, `MATCHING-ENGINE.md`, [entity-inventory.md](../architecture/entity-inventory.md) §2.6, §2.9

## Context

Comparison needs one product with many merchant offers. Merchant feeds describe the same product with
different titles, SKUs, pack labels and sometimes different or missing EANs. The prototype resolves
feed rows to products with `intel.js match()` (EAN, brand, title similarity, pack, variant,
ingredients) and implements merges with a canonical pointer. `README.md` calls the canonical graph
"the moat"; cross-shop price history is impossible without it.

## Decision

Three separate identities:

| Table | Identity | Key constraints |
|---|---|---|
| `products` | Canonical product owned by Comparo | `slug` unique; `reference` unique (nullable); `ean` indexed, **not** unique |
| `merchant_products` | A merchant's listing (feed identity) | unique (`merchant_id`, `merchant_sku`); `product_id` nullable = unmatched |
| `offers` | The current commercial terms of a matched listing | `merchant_product_id` unique (1:1); `product_id`, `merchant_id` denormalised for hot-path queries |

Rules:

1. A merchant listing maps to **at most one** canonical product. `merchant_products.product_id` is null
   until matching (Phase 2) or a staff decision resolves it.
2. An offer exists only for a matched listing. Its `product_id` equals the listing's `product_id`.
3. **Merges never delete.** The merged product gets `status = merged`, `merged_into_id` and `merged_at`;
   its row, slug and URLs keep resolving and redirect (301) to the survivor. EAN is not unique because
   merged duplicates keep theirs.
4. Variants (flavour, pack) are `product_variants` rows (`unique(product_id, kind, slug)`); an offer
   records `variant_label` / `pack_label` as supplied.
5. Matching confidence, buckets (`≥ 90` auto, `≥ 65` confirm, else unmatched) and the prototype's
   scoring are ported with parity against `tests/Fixtures/PrototypeParity/matching.json` (Phase 2).

## Consequences

- Price history is keyed on offer and product (ADR-0003), so it survives listing re-imports.
- A mis-match is corrected by changing one `merchant_products.product_id` and emitting an event; no
  history is rewritten.
- Product merges need an audit entry (`audit_logs`) and permission `catalogue.merge`.
- A `product_identifiers` table (multiple GTINs per product) is described in the entity inventory and
  is not created yet (planned with Phase 2).

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Offer rows carry the product identity (no listing table) | Re-import of a renamed SKU would orphan history; unmatched rows would have nowhere to live |
| Unique EAN on `products` | Merged duplicates and multi-GTIN products make it false in practice |
| Hard-delete merged products | Breaks inbound links and historical references |
