# Commercial OS

`/commercial` — 14 sections: Overview, Accounts, Subscriptions, Plans, Invoices, Affiliate revenue,
Sponsored, Sales pipeline, Renewals, API & data, Revenue analytics, Forecast, Governance, Settings.

## How Comparo makes money — five streams, none load-bearing alone

| Stream | What it is | Where it is traceable |
|---|---|---|
| Affiliate | Commission when a user we sent converts | `S.affiliate.daily` |
| SaaS | Merchant subscriptions for tools | `S.cx.subscriptions` |
| Media | Sponsored placements, always labelled | `S.cx.campaigns` |
| Data | API plans, overage and reports | `S.cx.apiUsage`, `S.cx.reports` |
| Partnerships | Exclusives and enterprise agreements | `S.cx.exclusiveAgreements` |

## What merchants pay for — and what stays free

Free forever: profile, feed import, review replies, three active deals, 30-day analytics.
Paid: deeper analytics, benchmarking, review and feed analytics, campaign inventory, API volume,
webhooks, exports, team seats, automation, custom reporting, support tier.

**Merchants never pay for rank.** Not a single entitlement touches ComparoRank, Trust Score, review
scores or compliance decisions. The Governance tab asserts this against live data.

## Every number traces to an entity

MRR sums subscriptions. ARR is MRR × 12 and excludes one-off campaign revenue. Affiliate revenue is
the commission ledger. Sponsored revenue is delivered campaign spend. API revenue is the Business
plan plus observed overage invoice items. Receivables are open and past-due invoices, aged from their
own due dates. Concentration is per-merchant revenue over the total.

## Working actions

Plan up/downgrade (reprices the subscription and MRR), cancel and resume, mark invoice paid, void,
issue a credit note, create a discount, approve/pause/reject a campaign, invoice a campaign, advance
or win/lose an opportunity, secure a renewal, create and revoke prototype API keys, record a report
purchase, export invoices as CSV — each permission-checked and audit-logged.

Future services: CommercialAccountService, SubscriptionService, EntitlementService, BillingService,
InvoiceService, CampaignService, SalesCRMService, RenewalService, AffiliateRevenueService,
APIUsageService, RevenueAnalyticsService.
