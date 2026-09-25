# Growth OS

`/growth` — a growth operating system, not a dashboard. Every opportunity points at a real entity,
every insight names its source data, and every suggested action creates a task someone owns.

## Sections

Overview (flywheel) · Opportunities · Merchant acquisition · Pipeline · Affiliate sales · Creators ·
Referrals · Content engine · SEO opportunities · Community growth · Newsletter · Campaigns ·
PR & research · Market expansion · Experiments · Growth analytics · Briefs.

## The flywheel

```
DISCOVERY  users · searches · community
   ↓
SUPPLY     merchants · products · offers
   ↓
INTELLIGENCE prices · reviews · trust · market data
   ↓
DISTRIBUTION SEO · AI search · newsletter · creators · PR
   ↓
MONETISATION affiliate · partnerships · exclusives
   ↓  more discovery
```

The Overview tab renders every headline metric inside the loop stage it belongs to, so nobody has to
guess whether a number is a cause or an effect.

## Growth Opportunity Score (0–100)

Demand (20) + commercial value (18) + data availability (12) + market gap (14) + user impact (12) +
SEO potential (12) + revenue potential (14) + low-effort bonus (8).

Opportunities are generated, never hand-written. Current generators: zero-supply searches,
under-supplied products, non-monetised merchant traffic, high-scoring uncontacted prospects, weak
markets, publishable content, ready research stories, unanswered community questions. Each row opens
to show its evidence, the data used, the time range, the confidence and the component points.

## Insight engine

Deterministic queries over prototype data — **explicitly not AI**. Each insight carries the data it
used, the range it covers and a confidence level, and converts to a task in one click. Anything
labelled AI in this product is either Ask Comparo (structured answers from internal data) or clearly
marked as an indicator.

## Growth tasks

Fields: owner, priority, due date, status, impact, effort. Kanban: Backlog → Planned → In progress →
Review → Done. Completing a task records a line in the **impact log** ("Merchant onboarded, +318
offers, France coverage 41 → 58") so the loop closes.

## Automations

Growth automations may **suggest**, **create a task** or **notify**. They never send an external
sales message. Rules include non-monetised traffic, zero-result SEO opportunities, research stories
from confirmed price anomalies, prospect qualification, creator tier review, unanswered-question
escalation and merchant churn risk.

## State

Stores added: `gxStage`, `gxOwner`, `gxOppStatus`, `gxTasks`, `gxProspects`, `gxCreators`,
`gxCampaigns`, `gxContent`, `gxNewsletters`, `gxResearch`, `gxExperiments`, `gxAuto`, `gxLinks`,
`gxSocial`, `referralInvites` — all persisted and migrated per STATE-MANAGEMENT.md.

## Future services

GrowthService · MerchantAcquisitionService · AffiliateCRMService · CreatorService · ReferralService ·
ContentOpportunityService · NewsletterService · ResearchService · GrowthAnalyticsService.
Each maps to one block of `growth.js`, which is pure functions over the seed graph.
