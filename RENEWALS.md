# Renewals

Every non-cancelled subscription has a renewal record: merchant, plan, date, days remaining, ARR,
owner, health, risk and status.

## Renewal risk

Derived from account health plus concrete signals: past-due invoice, paused subscription, unstable
feed, no merchant login. Levels Low / Medium / High with the signals listed, never a bare score.

## Playbook

High risk: resolve the open billing or feed issue **before** any commercial conversation, then present
the 90-day ROI report, then offer a plan change — not a discount.
Medium: share the ROI report, check unused entitlements, discuss an annual agreement.
Low: confirm the date, explore a second market.

## Expansion and downsell

Free → Pro → Growth → Enterprise expansion is proposed from usage signals (API usage, plan limits hit,
click volume, market count). Downgrade is always available as an alternative to cancellation.

## Churn

Tracked by reason (voluntary, payment failure, business closure, policy/compliance, other), plan,
market and tenure. Seeded: one missing-feature churn, one payment failure, one temporary pause.

## Merchant success

Stages: Onboarding → Activated → Growing → At risk → Expansion → Renewal. Activated means verified,
feed connected, offers live and profile complete. Tasks are concrete: improve match rate, add
shipping rules, respond to reviews, launch a first deal.
