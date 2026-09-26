# ADR-0018: Business decisions baseline (D-01…D-29)

- Status: Accepted
- Date: 2026-09-26
- Related: D-01…D-29 ([open-decisions.md](../architecture/open-decisions.md)), A-01…A-29
  ([OPEN-DECISIONS.md](../autonomy/OPEN-DECISIONS.md)); ADR-0004, ADR-0006, ADR-0007, ADR-0008, ADR-0015,
  ADR-0017

## Context

`open-decisions.md` listed 29 business decisions the repository could not settle, each with a safe,
reversible default. On 2026-09-26 the repository owner delegated all of them to the engineering
organisation ("rozhodni všechny body", i.e. "decide all the points"). Every open point needs a
decision so that later phases have fixed inputs. None of the decisions may pretend that a contract,
an account or a legal sign-off exists when it does not.

## Decision

Each D-xx is decided by four criteria, applied in this order: (1) safest for users and legally
conservative, (2) reversible through configuration, a feature flag or data, (3) consistent with the
existing code and ADRs, (4) realistic for a first launch. The documented safe default is adopted
unless a concrete reason amends it (D-06, D-10, D-11, D-24 and D-29 are amended or refined).

The full reasoning for each decision is the **Decision (2026-09-26)** block in `open-decisions.md`.
Every planned flag is added as a `Feature` case and defaults to **off** (A-17: a missing key already
fails closed).

| ID | Decision | Config / flag / data | Production prerequisite |
|---|---|---|---|
| D-01 | Launch markets DE + AT only; the other 25 are inactive data; US/GB/CH/NO out of v1 | `countries.is_active`; planned `comparo.markets.launch` = `DE,AT` | D-11 legal sign-off for DE/AT |
| D-02 | UI in EN + DE; content in source language; `/{locale}` reserved; hreflang only for enabled locales | `app.locale` = `en`; planned `comparo.locales.enabled` = `[en, de]` | German legal pages reviewed by counsel |
| D-03 | Network-agnostic adapters, signed postback, CSV import; Direct first; Awin is the first API adapter (sandbox only) | planned `comparo.affiliate.networks` = `[direct]`; flag `affiliate-network-api` off | Network contracts, publisher accounts, credentials |
| D-04 | No live invoicing; tax-exclusive merchant prices; draft invoices only | ADR-0006; flag `billing-live-invoicing` off | **Tax advisor sign-off** |
| D-05 | `ManualInvoiceProvider` only; Stripe adapter in test mode behind a flag; v1 does not charge | planned `comparo.billing.provider` = `manual`; flag `billing-stripe` off | Provider account and KYC, live keys, CFO decision, D-04 |
| D-06 | ECB daily reference rates into `exchange_rates`; warning after 4 days; unknown after 14 days (once the import exists) | planned `comparo.fx.source` = `ecb`, `stale_warning_days` = 4, `max_rate_age_days` = null → 14 | None |
| D-07 | One price per listing in feed currency, plus an optional `offer_market_prices` override | planned table | None |
| D-08 | 24 h / 4 h / 1 h internal targets with alerts; published only after 4 weeks at ≥ 90 % | planned `comparo.moderation.targets_hours`, `publish_sla` = false | Moderator staffing plan |
| D-09 | Retention schedule as in D-09; nightly purges; audit log 6 years, pseudonymous | existing `comparo.feeds.*` and `comparo.search.analytics.*`; planned `comparo.retention.*` | **DPO/Legal sign-off** |
| D-10 | Versioned weights; `ranking.publish` + second approver (four-eyes); audit entry + public changelog; prototype-v1 weights and quirks kept | `ranking_versions` / `ranking_weights`; planned permission `ranking.publish` | Named methodology owner and approver |
| D-11 | `unknown` = informational prices, no CTA (ADR-0007, not config); a market is activated only after Legal signs its baseline; rules expire after 12 months | `product_compliance_rules`, `countries.is_active`; planned `expires_at`, `comparo.compliance.rule_review_months` = 12 | **Legal sign-off of DE/AT baselines** |
| D-12 | Per-session self-declaration age gate; no date of birth, no documents | `countries.minimum_age`; planned `comparo.compliance.age_gate` | **Legal confirmation per launch category** |
| D-13 | Measured delivery only; Promise tiers built, flag-disabled; no deposits or payouts | flags `delivery-promise`, `delivery-promise-48h` off | Before enabling: legal opinion on the payments structure |
| D-14 | Buyer tiers modelled as entitlements, not sold; XP accrues, no redemption | flags `buyer-subscriptions`, `xp-redemption` off | Before enabling: D-04 OSS, consumer terms |
| D-15 | Orders from evidence only; shopper-reported delivery events, provisional 48 h; minimum 8 delivered orders | planned `comparo.orders.shopper_report_provisional_hours` = 48 | None |
| D-16 | Only figures derived from data; scenarios labelled; widgets without a data source hidden | presenters and tests (no config) | None |
| D-17 | Disclosure interstitial, 3 s cancellable auto-continue; category commission range; merchant rate hidden | planned `comparo.affiliate.interstitial_seconds` = 3; flag `merchant-commission-display` off | **Legal approval of disclosure copy**; network terms |
| D-18 | Advisory juries; staff decision with statement of reasons; illegal-content log within 24 h; DSA fields captured | planned `comparo.governance.juries_binding` = false, `comparo.moderation.illegal_content_log_hours` = 24 | **Legal: applicable DSA duties, point of contact, copy** |
| D-19 | No imported ratings; only reviews held on Comparo | none needed | None |
| D-20 | Visibility surfaces are the one inventory; position 1 never sold; manual booking only | planned `comparo.visibility.holdback_ratio` = 0.2; flag `self-serve-ad-booking` off | Before the first paid placement: ad-labelling legal review, D-04/D-05 |
| D-21 | Commercial gate on every commercial route incl. `/developers`; free API tier self-serve | flag `developers-gate-exempt` off | None |
| D-22 | Measured delivery replaces the seeded signal at n ≥ 8; returns not a trust input | planned `comparo.trust.returns_as_input` = false | None |
| D-23 | Server-side salted rotating hashes only (truncated IP + UA class); no client-side fingerprinting | planned `comparo.fraud.client_fingerprinting` = false, `hash_salt_rotation_days` = 30 | **DPO: legal basis, legitimate-interest assessment, ePrivacy** |
| D-24 | Default market DE, stated on the page; no crawler redirect; IP suggestion off | existing `comparo.default_market` = DE; planned `comparo.markets.ip_suggestion` = false | None |
| D-25 | Deactivate after 2 missed runs / 7 days unseen, with the mass-removal hold (already built); freshness thresholds move to config | existing `comparo.feeds.*`; planned `comparo.offers.freshness` | None |
| D-26 | All publication thresholds are named config values set to the prototype numbers; changes via the changelog | existing `min_demand_sessions` = 3; planned `comparo.thresholds.*` | Methodology owner (D-10) |
| D-27 | Provider-agnostic outbound mail; forwarded-email verification disabled | `MAIL_MAILER`; flag `verification-forwarded-email` off | Provider account and DPA, DNS (SPF/DKIM/DMARC), credentials |
| D-28 | Live rooms off; history recorded; Reverb when enabled | flag `live-rooms` off | Before enabling: dependency approval, moderation staffing |
| D-29 | `pricing.review` = existing `pricing.anomalies.review`; `growth.view` / `commercial.view` confirmed; merged roles get read-union plus write-intersection | `Permission`, `StaffRole` (already conform) | Assign staff to roles |

All A-01…A-29 are CONFIRMED. A-12 is AMENDED so that `/go` enforces the destination domain from
Phase 5. A-24 needs DPO verification before production.

### Still requires a human

These cannot be done by engineering and must not be simulated in code or copy:

1. **Legal, tax and DPO sign-off before production:** D-04 (tax advisor: invoices, reverse charge,
   OSS, VIES, record retention), D-09 (retention schedule, privacy notice), D-11 (DE/AT compliance
   baselines), D-12 (whether an age self-declaration is adequate), D-17 (affiliate disclosure copy),
   D-18 (DSA applicability, point of contact, notice and appeal copy), D-23 (legal basis for fraud
   signals), A-24/F-16 (search analytics k-threshold), and the German legal pages (D-02).
2. **Sign-off before enabling a disabled feature:** D-13 (legal structure for deposits and payouts),
   D-14 (consumer terms, OSS), D-20 (ad labelling before the first paid placement).
3. **Contracts and accounts:** affiliate network contracts and publisher accounts (D-03), a payment
   provider account with KYC (D-05), an email provider with a DPA (D-27), and a geolocation database
   licence, only if the IP suggestion is ever enabled (D-24).
4. **Credentials and infrastructure:** live API keys, postback secrets, sending-domain DNS records
   (D-03, D-05, D-27), all kept in a secret store and never committed.
5. **People:** a named methodology owner and second approver (D-10/D-26), a moderation staffing plan
   (D-08), and assignment of staff to roles (D-29).

## Consequences

- Later phases have fixed inputs. Every value is config, a flag or data, so changing a decision is a
  config or data change plus a changelog or ADR entry, not a refactor.
- A production launch is blocked until the items in "Still requires a human", group 1, are signed
  off. Staging and pilot environments are not blocked.
- The orchestrator applies the planned config keys and flags in the phases that use them. This ADR
  does not change code or config.
- Invariants are unchanged. Ranking never reads commercial data (D-10, D-20), compliance runs before
  serialization (D-11), and nothing is presented as complete when it is not (D-16).

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Leave the D-xx open until each named owner decides | The owner explicitly delegated them. Open inputs block Phases 5–15. |
| Per-market "unknown → blocking" compliance matrix (D-11 default) | Market-level activation after sign-off is stricter and simpler. ADR-0007 makes `unknown` semantics a code decision, not config. |
| Charge from day one with Stripe live (D-05) | No tax sign-off (D-04) and no provider account. Charging is irreversible for customers. |
| Treat the engineering defaults as legally approved | Nobody with that competence has signed off. Pretending otherwise breaks invariant 7. |
