# Subscriptions

`Subscription { merchant, plan, planVersion, status, billingPeriod, listPrice, price, currency,
discountPct, taxRate, taxMode, start, renewal, trialStart, trialEnd, cancelledAt, cancelAtPeriodEnd,
pausedUntil, mrr }`

Statuses: Trialing, Active, Past Due, Paused, Cancelled, Expired.
Periods: monthly, annual (annual discount configurable, currently 17 %), custom for Enterprise.

## Plan changes reprice

Changing a plan recomputes list price, applies the account's existing discount, and recalculates MRR
from the billing period. Verified: GROWTH €399/mo → PRO €149/mo moves MRR by exactly that amount.
List prices are held in EUR; the account's billing currency is a separate field so reporting
normalisation never mistakes a EUR list price for a local amount.

## Proration

Before a change the console shows an estimated proration: unused credit for remaining days, the new
charge for the same days, the net, tax and total — labelled **estimated**, because production billing
recalculates at the provider with exact cycle dates and jurisdiction.

## Trials

14-day default. The merchant sees days remaining and features used. On expiry, features degrade
gracefully and **no merchant data is deleted**.

## Cancellation

Cancel at period end by default, immediately on request. A reason is optional and recorded
(too expensive, not enough value, missing feature, business closed, temporary pause, other). Pause is
supported where configured, with a restart date.
