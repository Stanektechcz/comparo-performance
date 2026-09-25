# ADR-0008: Market, locale and currency are independent

- Status: Accepted
- Date: 2026-09-25
- Related: C-11, C-22, C-33, D-01, D-02, D-06, D-24, ADR-0009

## Context

The prototype's topbar has one segmented control for country, currency and language. Country decides
shipping, coupon eligibility and compliance; currency decides display; language changes nothing
(AUDIT B9). Coupling them would, for example, force Czech shoppers into Czech UI, or make a German UI
imply German compliance rules.

## Decision

1. Four independent concepts:

   | Concept | Decides | Source of truth |
   |---|---|---|
   | **Market** (destination country, ISO 3166-1 alpha-2) | shipping zone, coupon eligibility, compliance jurisdiction lookup, default display currency | `countries` |
   | **UI locale** | interface language only | `APP_LOCALE`, user preference (planned) |
   | **Currency** | display of money | market default (`countries.currency_id`) or user choice |
   | **Compliance jurisdiction** | legal status of a product | `product_compliance_rules` keyed by `country_id` |

2. **Market resolution order** (`App\Domain\Platform\Markets\MarketResolver::resolve()`, applied per request
   by `App\Http\Middleware\ResolveMarket`, which binds an immutable `MarketContext` and adds `market` to
   Laravel `Context`):
   1. `?market=XX` query parameter (validated against active `countries`),
   2. `comparo_market` cookie (`config('comparo.market_cookie')`),
   3. `COMPARO_DEFAULT_MARKET` (`config('comparo.default_market')`, default `DE`).

   An invalid value falls through to the next source. The resolved market is part of every
   compliance-sensitive cache key.
3. **Comparison currency is EUR** (`config('comparo.comparison_currency')`). Ranking and market statistics
   compare amounts in one currency. Display conversion uses dated rows in `exchange_rates`
   (`base_currency`, `quote_currency`, `rate`, `source`, `effective_at`), loaded by
   `App\Domain\Pricing\Currency\ExchangeRates::conversion($from, $to, $at)` into a `CurrencyConversion`,
   and is labelled indicative.
   The rate source is D-06.
4. Locale URL prefixes (`/{locale}`) are reserved; hreflang is emitted only for enabled locales (C-33).
   Product data stays in its source language until D-02 is decided.

## Consequences

- A crawler with no cookie always sees the default market (D-24); canonical URLs do not vary by cookie.
- `?market=` links are shareable and deterministic; the resolver may set the cookie for later requests.
- Money never changes currency implicitly (ADR-0009); conversion is an explicit step with a named rate.

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Derive market from UI locale | Many markets share a language; expats and cross-border shoppers break |
| GeoIP as primary source | Non-deterministic for caches and crawlers; kept as a possible hint only |
| Store prices converted to EUR only | Loses the merchant's actual currency and rounding |
