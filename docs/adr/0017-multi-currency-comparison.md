# ADR-0017: Multi-currency total-price comparison

- Status: Accepted
- Date: 2026-09-26
- Related: A-29, D-06; `docs/architecture/phase-3-search.md` §7, `docs/modules/pricing.md`; ADR-0004
  (ranking purity), ADR-0008 (market/locale/currency independence), ADR-0009 (money as integer minor
  units)

## Context

`ProductOfferComparison::lowestTotal` compared `Money` values of different currencies directly and would
throw as soon as a market had offers priced in two currencies — a latent bug the prototype never exercised
(every prototype market is single-currency) but that Phase 3's search index, which sorts across all
offers in a market, makes reachable. The per-market snapshot needed a defined, tested answer for "lowest
total" and "market baseline" when currencies mix, without touching the single-currency path that
`tests/Unit/Parity/**` and `tests/Feature/Parity/**` hold byte-identical to the prototype.

## Decision

### Comparison currency and rate source

`comparo.comparison_currency` (EUR) is the one currency every cross-currency amount is normalised into.
`Pricing\Currency\ExchangeRates` — bound `scoped` per request/job (`AppServiceProvider`) — resolves the
dated rate valid at the evaluation instant from `exchange_rates`, with a per-instance memo keyed by
(from, to, exact instant) so one results page's repeated (currency pair, instant) lookups cost one query,
not one per card; a **miss is never cached**, since a rate can still be written by another part of the
same request (e.g. a synchronously dispatched job on a `sync` queue connection) and a cached miss must
never shadow a rate that becomes available moments later. `ExchangeRates::comparisonRates(...)` builds a
`Pricing\Currency\ComparisonRates` table for a request's actual currency set; a currency absent from the
table, or explicitly mapped to `null`, has no known rate.

### Everything not in the market's shared currency is either converted or excluded — never guessed

- **Market baseline** (`Pricing\MarketStats\MarketStatsCalculator`): when the counted listings share one
  currency (every prototype market, always), the baseline is computed on raw minor units exactly as
  ported — unchanged, parity-proven. When they span several, each listing's total is formed in its own
  currency first (a free-shipping threshold is evaluated there, not after conversion), then converted;
  a listing whose currency has no known rate is left out of the baseline entirely.
- **Ranking inputs** (`Offers\Queries\ProductOfferComparison::baselineAmounts`): an offer already in the
  market's baseline currency uses its own minor units; a mixed-currency offer is converted only when the
  comparison rates target that same baseline currency, and only when *both* its total and its shipping
  convert — a partial conversion is treated as no conversion. An offer that cannot be converted keeps its
  raw amounts but is flagged `comparable: false`, so ComparoRank's price factor gives it **no price or
  shipping advantage or penalty** (A-29) rather than comparing an apple to an unconverted orange.
- **Tie-breaks / listing order** (`inListingOrder`, `lowestTotal`, `comparableAmounts`): single-currency
  listings sort by raw minor units and offer id, exactly as before. Mixed-currency listings sort by the
  *exact*, unrounded comparison-currency amount (`ComparisonRates::exactMinor`, bcmath, 6 decimal places
  of a minor unit) so a rounding artefact never flips a close tie; an offer with no known rate sorts after
  every comparable offer of the same rank. `lowestTotal` is reported **in the winning offer's own
  currency** (never converted for display) — only the *comparison* that finds it uses the comparison
  currency.
- **Shipping and coupon terms** (`Pricing\LandedPrice\MerchantTermsConverter`, at the query layer, before
  the pure `LandedPriceCalculator` ever runs): a merchant's shipping zone rate, free-shipping threshold and
  coupon amounts are converted into the *offer's own* currency, not the comparison currency — the pure
  calculator always works in one currency, as ADR-0009 requires. Without a known rate: the shipping cost
  is unknown, so the offer is excluded from the public comparison as `shipping_unavailable`; a coupon that
  carries an amount (fixed discount or a minimum-order threshold) is silently not applied — a saving that
  cannot be verified is never shown — while a percentage-only, no-minimum coupon needs no rate and always
  applies.

### API surface

`meta.currency` is always the comparison currency (EUR); `meta.display_currency` is the market's own
currency. `meta.market_min_currency` is **not** always the comparison currency: it is the offers' shared
currency when the market's counted offers are all in one currency, and only the comparison currency when
they span several — both `market_min` and `market_min_currency` are `null` when there is no market
minimum. Cached comparison pages (keyed by product × market × currency × ranking version × product
version) store only scalars (minor units, currency codes, ISO instants) — never a `Money` object or a
`ComparisonRates` table — so a cache read never needs the request-scoped rate memo the write path used.

## Consequences

- Single-currency markets (every prototype market today) are provably unaffected: the code path taken is
  the same one that existed before this ADR, still covered by the same parity fixtures.
- A market with genuinely mixed currencies degrades gracefully rather than throwing: an unconvertible
  offer is excluded from ranking advantage and, if its shipping cannot be priced, from the public
  comparison outright — never silently mispriced.
- `ExchangeRates` must stay request/job-scoped (not a singleton): its miss-is-never-cached memo assumes a
  fresh instance sees the database's current state each time, which a longer-lived singleton across
  requests would break.
- A future ECB-fed rate import (D-06) changes only what `exchange_rates` contains, not this decision — the
  "no known rate → exclude, never guess" rule is independent of where rates come from.

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Fall back to a stale or default rate when none is dated for the instant | Silently mispricing a comparison is worse than excluding an offer; `ExchangeRates` already looks for the most recent rate at-or-before the instant, so "none found" means genuinely none exists |
| Convert everything to the comparison currency for display | Breaks the existing single-currency display contract (prices shown in the offer's own currency) and would require converting on every cache read, not just at write time |
| Round comparison amounts before comparing for ties | A rounded comparison can flip a genuinely-different exact ordering into a tie (or vice versa) purely from rounding; `exactMinor` avoids that by comparing unrounded bcmath amounts |
| Apply a coupon's fixed amount unconverted, in the offer's currency label as-is | Would show a saving in the wrong currency's units, silently overstating or understating the true discount |
