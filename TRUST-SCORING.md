# Trust scoring

Two separate scores, deliberately not mixed:

* **Merchant Trust Score 2.0** — public, explainable, consumer-facing.
* **Merchant Risk Score** — internal, never shown to consumers or merchants.

## Merchant Trust Score 2.0 (0–100)

| Input | Weight |
|---|---|
| Business verification | 14 |
| Review score | 14 |
| Complaint rate | 10 |
| Complaint resolution | 10 |
| Verified review ratio | 8 |
| Pricing accuracy | 8 |
| Feed uptime | 8 |
| Account maturity | 6 |
| Merchant response rate | 6 |
| Order verification | 6 |
| Shipping accuracy | 6 |
| Link health | 4 |

Community reports apply a capped penalty (max 8). Labels: ≥ 90 Highly trusted, ≥ 78 Trusted,
≥ 65 Generally reliable, ≥ 50 Mixed signals, below that Low trust.

### Public breakdown

Shop profiles show six plain-language signals instead of internal weights: business verified,
pricing accuracy, customer satisfaction, shipping reliability, complaint resolution, data freshness.
The full weighted breakdown is available behind "How this score is built".

### Trust history

7 d / 30 d / 90 d / 1 y series, anchored on the current computed score. Merchants and admins see the
same series. Used to detect drift before it becomes a complaint.

## Merchant Risk Score (internal, 0–100)

Signals: unverified ownership, complaint rate vs platform median, feed instability, broken outbound
links, price-accuracy drift, community reports, low response rate, plus decayed risk events
(rating spikes, duplicate reviews, review bursts, fake coupons, feed conflicts).

Levels: LOW < 22, MEDIUM ≥ 22, HIGH ≥ 42, CRITICAL ≥ 62.

Queue: `/intel → Risk & integrity`. Actions: review, request verification, restrict offers,
suspend, clear flag — each permission-checked and audit-logged.

## User trust (internal)

Account age, verified purchases, helpful votes, reviews published, upheld reports, spam strikes.
Never displayed as a number. Publicly users have badges and reputation only.

## SLA metrics

Feed freshness, review response, complaint response and offer-correction time, each with target and
actual. Visible to the merchant for their own account and to admins for all accounts.

Future services: **TrustService**, **RiskService**.
