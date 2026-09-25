# Affiliate system

## Principles

1. The click log is append-only and anonymous: a rotating session hash, never a raw IP or a device
   fingerprint kept beyond attribution.
2. Commission never influences ranking. Sorting is by price, total, rating, delivery or popularity;
   sponsored positions are separate, budget-funded and always labelled.
3. Every outbound link is disclosed to the user before it is followed.

## Data model

```
AffiliateNetwork ─< AffiliateProgram ─ Merchant
AffiliateClick ─< AffiliateConversion ─< Commission
```

See [DATABASE.md](DATABASE.md) for columns. Three monetisation modes coexist per merchant:
direct affiliate program, network-mediated program, and platform CPC/CPA billing.

## Redirect: `/go/{merchant}/{product}`

```
1  resolve merchant + product; 404 on unknown pair
2  reject if merchant is suspended, offer inactive, or the product's compliance status
   for the visitor's country is not_allowed / prescription_only
3  enqueue AffiliateClick (uuid, merchant, product, merchant_product, placement, campaign,
   country, currency, session_hash, user_id?, sub_id, network, user_agent_class, created_at)
4  build the destination URL from the program's tracking_template
5  302 with Referrer-Policy: strict-origin-when-cross-origin, rel="nofollow sponsored"
```

Target: p99 < 40 ms. The queue write must never delay the redirect; a dropped click is preferable
to a slow one, and the merchant-side numbers reconcile against network reports anyway.

### Tracking parameters

| Parameter | Meaning |
| --- | --- |
| `utm_source` | always `comparo` |
| `utm_medium` | `affiliate` |
| `utm_campaign` | placement family (`product_offer_table`, `deal_hub`, `homepage_deals`, `search_results`, `compare_table`) |
| `subid` | `{program.sub_id_prefix}-{click_uuid}` — the reconciliation key |
| `cc` / `cur` | country and currency shown to the user |

`sub_id` is the only join key we rely on. Networks that truncate sub IDs are configured with a
short prefix and a base36 click counter instead of the full uuid.

## Conversion ingestion

Two paths, both idempotent on `(network_id, network_order_id)`:

- **Postback / webhook.** `POST /api/v1/webhooks/networks/{network}`, HMAC-signed with the
  network's `postback_secret`; replay window 5 minutes.
- **Report pull.** Nightly worker per network API for the last 30 days, overwriting `pending`
  rows only. Approved and rejected states are terminal.

Attribution: match `sub_id` to a click; if absent, fall back to (merchant, session_hash,
timestamp within the program's `cookie_days`). Unmatched conversions are stored with
`click_id = null` and surfaced in the admin console as attribution loss.

## Metrics

```
clicks              count(affiliate_click)
unique clicks       count(distinct session_hash)
conversions         count(affiliate_conversion where status = approved)
conversion rate     conversions / clicks
revenue             sum(order_value)
commission          sum(commission.amount)
EPC                 commission / clicks
AOV                 revenue / conversions
CTR                 clicks / offer impressions
```

Merchant dashboards show these for today, 7 days and 30 days plus breakdowns by product, country,
placement and deal. Admin sees the same aggregated across merchants, plus commission by network and
attribution loss rate.

## Fraud and hygiene

Bot user agents and datacentre ranges are classified at redirect time and flagged, not blocked.
Clicks with the same session hash on the same offer within 5 seconds collapse into one. Conversion
values above 10× the merchant's AOV are held for manual review. A merchant whose feed prices
diverge from its landing pages by more than 3 % on repeated sampling loses "verified" status.

## Disclosure

Every page with outbound links carries the disclosure line, and the interstitial at
`/go/...` repeats it with the commission rate and cookie window before the user proceeds:

> Some links are affiliate links. If you buy through them we may earn a commission — your price
> does not change.

## CPC and CPA billing

For merchants on CPC, each valid click debits `sponsored_placement.spent` at the agreed
`cpc`; the placement pauses automatically when the budget is exhausted. CPA merchants are billed
from approved conversions on the monthly invoice. Both appear on the merchant's invoice lines
alongside the subscription fee.
