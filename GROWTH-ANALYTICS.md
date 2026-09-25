# Growth analytics

## Channels

Organic Search · AI Search · Direct · Community · Referral · Creator · Newsletter · Social ·
Merchant referral. Per channel: users, activated users, activation rate, merchant clicks,
conversions, revenue, revenue per user, and a CAC column that is a **placeholder** — there is no paid
acquisition yet and the prototype does not pretend otherwise.

## Lifecycle

Visitor → Registered → Activated → Engaged → Contributor → Returning → Dormant, each with its
definition displayed. Activation means a saved product, price alert, review, follow or comparison.

## Funnels & cohorts

Activation funnel (registration → first search → first save → first alert → first return) with
drop-off; weekly cohorts with W1 and W4 retention; D1/D7/D30 retention proxies. Community, review and
merchant activation are tracked as separate funnels because they fail for different reasons.

## LTV proxy

Revenue per user by channel, plus activation rate. Labelled a proxy everywhere it appears — it is not
financial LTV and no margin, refund or repeat-purchase data exists in the prototype.

## Revenue splits

By channel, by market, by page type (product, comparison, shop, deal, guide, country) and by entity
(brand, product, merchant, category).

## Journeys & attribution

Named paths per channel (organic, AI search, community, creator, newsletter) plus first-touch,
last-touch and assisted models side by side. **Assisting content** — guides and reviews that
precede a merchant click without being the last touch — is reported explicitly, because last-touch
reporting systematically under-credits it.

## Drop-off detection

Where users leave before comparing, with the sessions affected and the concrete fix (for example
"offer table — no shipping data → improve merchant shipping coverage"), convertible to a task.

## Data quality

Where a metric rests on incomplete data, the confidence is stated next to it. Seeded prototype
figures are marked as such in PROTOTYPE-LIMITATIONS.md.
