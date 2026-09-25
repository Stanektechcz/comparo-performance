# Reviews

Reviews are the product's trust asset, so the rules are strict and the same for everyone.

## Product review

| Field | Notes |
| --- | --- |
| `rating` | 1–5, required |
| `sub_ratings` | value, quality, packaging, ease of use — only the ones that make sense for the category |
| `title`, `body` | body minimum 20 characters |
| `pros`, `cons` | short bullet lists |
| `recommends` | boolean, drives the "% would recommend" figure |
| `used_for` | less than a month / 1–3 months / 3–6 months / over a year |
| `experience` | beginner / intermediate / advanced / competitive (optional) |
| `photos` | optional, moderated |
| `merchant_id` | where it was bought, optional |

No medical or health disclosure is ever requested or stored.

## Shop review

Overall rating plus six dimensions: **delivery speed, shipping cost, customer support,
communication, product accuracy, returns / dispute handling**. The shop profile shows the
breakdown, verified order rate, response rate, average response time, complaints in the last 30
days and the resolution rate — the numbers that actually predict a good order.

## Verification

A review is verified when one of these matches:

| Method | Status shown |
| --- | --- |
| Affiliate conversion matched to the reviewer's session | Verified purchase |
| Order reference confirmed against the merchant feed | Order reference |
| Merchant confirms the order | Merchant-confirmed |
| Manual check by our team | Admin-confirmed |

We store a hash of the order confirmation and discard the document. Purchase details, order
values and addresses are never shown publicly.

## Trust score (internal)

Signals: verified purchase, account age, prior review history, helpful-vote ratio, duplicate or
near-duplicate text, submission bursts from one IP or device class, any merchant relationship, and
moderation history. The score is **never public** — it only routes items inside the moderation
queue. Public badges are earned facts (Verified Buyer, Established Reviewer, Helpful Reviewer,
Top Contributor), not scores.

## Anti-abuse

One review per account per target, three reviews per day, email verification required before the
first review is published, rate limiting per IP and account, spam heuristics (caps ratio,
punctuation runs, outbound links, generic praise, template repetition) and burst detection across
accounts hitting one merchant.

## Merchant replies

One official response per review. The merchant may edit it for 24 hours, request moderation of a
review they believe breaches the rules, and mark an issue as resolved — the resolved flag only
becomes visible when the reviewer confirms it. Merchants cannot delete, hide or reorder reviews.

## Aggregation

Ratings are computed from **published** reviews only, at read time. Nothing is stored by hand,
nothing is seeded, and structured data (`AggregateRating`) is emitted only when at least one
published review exists.

## Review insights and summary

Both are deterministic and computed from structured data — no model, no external service:

- **Insights**: reviews are matched against a keyword map per topic (Delivery, Price, Support,
  Packaging, Returns, Taste, Mixability, Label accuracy). Each topic reports the number of
  mentions and a Positive / Mixed / Negative label derived from the ratings of the matching
  reviews, plus a three-segment sentiment bar.
- **Summary**: a sentence built from review count, average rating, recommendation rate and the
  strongest positive and weakest topics. Labelled "Review summary" and explicitly described as
  computed from structured review data.

## Discovery

`/reviews` is the review hub: tabs (Latest, Most helpful, Product reviews, Shop reviews, Verified
only) plus rating and reviewer-country filters, with the catalogue-wide summary and insight bars
alongside.
