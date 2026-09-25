# Merchant acquisition

## Discovery sources

User suggestions · unmatched outbound domains · community mentions · search queries · admin entry ·
imported prospect lists. The prototype never scrapes or circumvents a restricted site.

## MerchantProspect

Name, domain, country, markets, categories, brands, discovery source, estimated relevance, priority,
stage, owner, notes and an activity timeline.

## Pipeline

Discovered → Qualified → Contact ready → Contacted → Replied → Negotiation → Onboarding →
Feed integration → Verified → Live, plus Rejected and Dormant. Stage chips filter the list and each
row advances one stage at a time with an audit entry.

## Qualification score (0–100)

Market relevance (14) + catalogue overlap (18) + user demand from search mentions (16) +
community mentions (8) + brand coverage (12) + catalogue size (12) + shipping coverage (10) +
affiliate potential (10). Outreach threshold: **65**.

## Next best action

Derived from stage and score: qualify, contact, follow up, request feed, negotiate affiliate,
offer premium profile, negotiate exclusive, review integration, or pause. Every prospect shows the
action *and* the reason for it.

## Outreach templates

Initial outreach · affiliate proposal · feed request · partnership proposal · exclusive deal
proposal · verification request · follow-up. Variables: `{merchant}`, `{market}`, `{products}`,
`{searches}`, `{clicks}`, `{rate}`, `{conv}`, `{trend}`. Copy is generated and copied to the
clipboard — the prototype sends nothing.

## Funnel

Prospects → Qualified → Contacted → Replies → Onboarding → Live, with stage-to-stage conversion, plus
a per-market breakdown against live merchant counts.

## Self-serve

`/for-merchants` explains the free profile, feed integration, analytics, deals, reviews and
affiliate partnership, shows example figures from listed merchants (clearly labelled as examples),
and states plainly that ranking is organic. Onboarding is five steps and resumable.
