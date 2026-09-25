# Attribution and affiliate optimisation

## Event stream

Prototype events: search, search_click, product_view, shop_view, deal_view, compare, save, follow,
review, merchant_click, coupon_copy. Stored per session in browser storage (last 120 events),
merged with the seeded stream for analytics. Suppressed entirely when consent is "essential only".

## Session journey

Search → product → compare → merchant → affiliate redirect, reconstructed per anonymous session with
country and device class. Visible in `/intel → Affiliate → Session journeys`.

## Conversion funnel

Searches → search clicks → product views → comparisons → merchant clicks → conversions, with
step-to-step drop-off.

## Affiliate metrics

CTR, CVR, EPC, AOV, clicks, revenue, commission per merchant, plus blended platform values.

## Commission normalisation

Direct → CPS, Awin → CPA, Impact → Hybrid, others → CPC. All models are normalised to **EPC**
(commission ÷ clicks) so merchants on different models can be compared on one axis.

## Attribution dimensions

First click, last click, campaign, placement, product, country, device category, session. Organic
source attribution (Google, Bing, AI search, direct, community, referral) reports visits, merchant
clicks, conversions, revenue and CVR.

## Affiliate link health

Detects broken links (404), redirect loops, missing tracking parameters, expired programmes and
unexpected destinations. Actions: mark fixed, hide offer. A broken link applies a hidden ComparoRank
penalty and creates a task.

## Commercial Opportunity Score (admin only)

Conversion × 6 + EPC × 40 + log(revenue) × 9 + inventory × 0.35 + relationship bonus.
Labels: Priority account, Growth account, Maintain, Low priority.

**This score never touches consumer ranking.** It exists for sales outreach, campaign planning and
exclusive-deal negotiation, and lives in a different table from ComparoRank by design.

## Merchant Performance Score (admin only)

Trust × 0.32 + conversion × 5 + offer freshness × 22 + feed uptime × 0.16 + rating × 12.

## Comparo exclusives

Exclusive coupon, exclusive price, cashback, bundle, limited-time promotion — each with views,
clicks, conversions, revenue, commission and usage. Merchants see only their own campaigns.

Future service: **AffiliateService** (click ledger, network adapters, attribution resolver).
