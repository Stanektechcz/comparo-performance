# Merchant SaaS

## Commercial account

Every merchant has a commercial account: status (Free, Trial, Paid, Enterprise, Paused, Past Due,
Cancelled), plan, billing country and currency, tax profile, owner, contract type, renewal type,
notice period, payment status, credits and internal notes.

## Plan distribution in the prototype

Deliberately realistic: 6 free, 1 trial, 3 Pro, 3 Growth, 1 Enterprise — plus one Past Due and one
Paused account, and one cancelling at period end with a stated reason.

## Value before cost

The merchant view leads with what Comparo generated (impressions, clicks, conversions, attributed
sales) and only then shows the plan price. Where attribution is incomplete we say **attributed
revenue**, never "revenue generated".

## Account health

Product usage, traffic, conversion, feed health, review response, billing status and trust — scored
0–100 with every component visible and its own detail line. Health drives renewal risk and the
next-best-commercial-action, not a sales quota.

## Upsell, honestly

Upsell score reads real signals: API usage against plan, deals at the plan limit, click volume,
market count. The recommendation names the signal. Downgrade is always offered instead of forcing a
cancellation, and a downgrade never deletes data.
