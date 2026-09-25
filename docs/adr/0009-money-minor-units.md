# ADR-0009: Money as integer minor units

- Status: Accepted
- Date: 2026-09-25
- Related: C-02, ADR-0008, ADR-0010, [modules/pricing.md](../modules/pricing.md)

## Context

The prototype stores float EUR and converts at display time. `DATABASE.md` proposes `numeric(12,2)`;
`API-ENDPOINTS.md` uses integer minor units. Binary floats cannot represent most decimal prices exactly.

## Decision

1. **Storage:** every amount is an unsigned `bigint` column named `*_minor` plus a `char(3)` ISO-4217
   currency (`price_minor` + `currency`, `amount_off_minor`, `min_order_minor`, `shipping_minor`,
   `free_shipping_threshold_minor`, `rrp_minor` + `rrp_currency`, …). `currencies.minor_unit` holds the
   ISO exponent (EUR 2, JPY 0).
2. **Domain:** `App\Domain\Shared\Money` (`minor:int`, `currency:string`) is immutable, validates the
   currency code, and refuses arithmetic or comparison across currencies
   (`InvalidArgumentException: convert explicitly first`). JSON form: `{"minor": 4390, "currency": "EUR"}`.
3. **Arithmetic:** integer only. Percentages are basis points; the landed-price calculator works at a
   scale of 10 000 and rounds half-up to the minor unit once, at the end.
4. **JS compatibility helpers** (`App\Domain\Shared\JsMath`: `round` = JS `Math.round`, `upperMedian`,
   `mean`, `clamp`, `roundTo`) are used **only inside scoring parity code**, where ratios and scores must
   match the prototype exactly. They are never used for money storage.
5. **API:** public API money is `{ "amount": int, "currency": "EUR" }` in minor units; Inertia props use
   the same integers and format on the client.
6. Display rounding (e.g. 0 decimals for CZK) is a presentation rule, never a storage rule.

## Consequences

- The landed price is exact; parity with the prototype's float arithmetic is still zero mismatches over
  7 209 offer × market cases (ADR-0010).
- Every new money column needs a currency column or a documented fixed currency.
- Cross-currency comparison requires an explicit conversion through `exchange_rates`.

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| `numeric(12,2)` / PHP `float` | Float drift; fixed 2 decimals is wrong for zero- and three-decimal currencies |
| brick/money or moneyphp | Adds a dependency for a small, well-tested value object; can be adopted later behind `Money` |
| Store EUR only | Loses merchant currency; conversion becomes irreversible |
