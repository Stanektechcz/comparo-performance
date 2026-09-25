# ADR-0003: Per-offer, append-only price history

- Status: Accepted
- Date: 2026-09-25
- Related: C-04, C-10, BACKEND-READINESS §4.4, ADR-0009

## Context

The prototype stores a product-level daily series (`p.hist.min`, `p.hist.avg`) and per-merchant series
for only three offers per product. Fake-discount detection, price badges, timing, merchant price
accuracy and price confidence all reason from partial data. Price history cannot be backfilled.

## Decision

1. **`price_snapshots` is per offer and append-only.** Columns: `offer_id`, `product_id`,
   `merchant_id`, `price_minor`, `currency`, `reference_price_minor`, `shipping_minor`,
   `shipping_country_code`, `availability`, `reason`, `source`, `feed_run_id`, `corrects_snapshot_id`,
   `observed_at`, `created_at`.
   - `reason` (`App\Domain\Pricing\History\SnapshotReason`): `first_seen`, `price_change`,
     `shipping_change`, `availability_change`, `scheduled`, `correction`, `prototype_import`.
   - `source` (`SnapshotSource`): `feed`, `merchant_api`, `manual`, `prototype_demo`.
2. **Append-only is enforced twice:**
   - Database triggers created in `2026_09_25_100500_create_price_history_tables.php`: on PostgreSQL a
     `BEFORE UPDATE OR DELETE` trigger calling `comparo_forbid_mutation()`; on SQLite `RAISE(ABORT)`
     triggers for update and delete.
   - Model guard `App\Models\Concerns\AppendOnly` throws
     `App\Domain\Platform\Exceptions\AppendOnlyViolation` on update/delete.
3. **Corrections are new rows** with `reason = correction` and `corrects_snapshot_id` pointing at the
   corrected row. Nothing is updated in place.
4. **`market_price_stats` is derived**, one row per (`product_id`, `market`, `stat_date`), holding the
   daily low (`min_price_minor`), optional average and offer count. `market` is an ISO-2 code or `ALL`.
   `source` is `aggregated` (computed from snapshots) or `prototype_demo` (imported synthetic series,
   demo environments only; `MarketPriceStat::isDemoData()`).
5. Price statistics (`PriceHistoryAnalyzer`: averages, lows, median, volatility, badge, timing, trend)
   run on the daily-low series and are pure.
6. `audit_logs` uses the same append-only mechanism (triggers + `AppendOnly`).

## Consequences

- Write volume is dominated by `price_snapshots`; on PostgreSQL the table is a partitioning candidate
  (monthly range on `observed_at`) once volume justifies it. Not partitioned yet.
- Retention and GDPR erasure do not apply to price rows (no personal data). Audit-log erasure conflicts
  are tracked in C-35 / D-09.
- Recomputing stats is always possible from snapshots; stats rows can be regenerated for a date range.
- Demo data can never be mistaken for measured data because `source` says so.

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Product-level daily series only | Cannot detect per-merchant fake discounts or measure price accuracy |
| Mutable latest-price row with `updated_at` | Destroys history; cannot be backfilled later |
| Soft-delete / update with audit trail | Weaker guarantee than a trigger; corrections become invisible |
