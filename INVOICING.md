# Invoicing

`Invoice { id, merchant, subscription, issued, due, currency, items[], subtotal, tax, total, status,
paidAt, timeline[] }`

Numbering pattern is configurable: `CMP-{YYYY}-{00000}`.
Statuses: Draft, Open, Paid, Past Due, Void, Credited.
Item kinds: Subscription, Sponsored campaign, API overage, Exclusive placement, Custom service, Credit.

## Detail and print

Invoice detail shows merchant information, items, tax, total, payment status and a timeline
(issued → sent → paid, or failed with retry). The print stylesheet strips navigation and chrome so
the browser print dialog produces a usable document.

## Credit notes

`CreditNote` records amount, reason, status and actor, and reduces a future invoice. Seeded examples:
a campaign under-delivery credit (applied) and a goodwill credit while affiliate tracking was broken
(available).

## Receivables

Open and past-due invoices are aged into Current, 1–30, 31–60, 61–90 and 90+ buckets from each
invoice's own due date. The Overview surfaces the total and the count; the Invoices tab shows the
buckets. Voiding an invoice removes it from receivables immediately.

## Campaign billing

A completed campaign can generate an invoice item for its delivered spend, with tax applied — the
link from media delivery to receivable is explicit, not implied.

## Export

Filter-aware CSV export of invoices (id, merchant, issued, currency, subtotal, tax, total, status),
gated on `commercial.export`.
