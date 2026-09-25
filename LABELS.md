# Labels

A badge is the one element a shopper reads *instead of* reading the table, which makes it the
most dangerous thing on a comparison site. Eight labels exist; every one obeys four rules.

1. **Computed, never granted.** Each label names the records it derives from and is recomputed on
   every render. There is no `has_badge` column anywhere in the data.
2. **Not for sale.** Not by a plan, an add-on, a campaign or a partner tier. The advertising page
   lists labels under "not for sale at any price" and `labels.js` is why that is true.
3. **States what it does not mean.** "We recommend" is not "best"; "Verified by users" is not an
   endorsement by us and a badly rated product can hold it.
4. **Revocable, and revocations are published** with their reason and any restoration date.

## The eight

| Label | Scope | Earned when |
|---|---|---|
| We recommend | product | cheapest quartile on price per gram of active, 5+ reviews, 90 % of the serving declared, 3+ shops, no compliance block — plus a named editorial read |
| Verified by users | product | 4+ reviews backed by a matched click or order reference, 1+ label photo, verified ≥ half the sample |
| Fully declared label | product | declared ingredients and carriers account for 97 %+ of the serving |
| Honest reference prices | shop | no upheld reference-price flag in 180 days |
| Measured delivery | shop × market | 8+ delivered orders in the reader's market |
| Answers fast | shop | replies to 55 %+ of reviews, median first reply under 48 h |
| Community favourite | product | top 3 by watchers in its category, 50+ watchers |
| Batch lab reports published | brand | public certificate, lot number matches the pack, newest report under 120 days |

Only the first is editorial; the other seven have no human in the loop at all. The editorial log
is published, including the products **held back** despite meeting every numeric criterion.

## Four labels we refused

"Trusted shop" (collapses four measurements into one word), "Best price" (true for a market, a
currency and a minute), "Official partner" (a commercial relationship that shoppers read as a
quality signal), "Editor's choice per category" (indistinguishable from paid placement).

## Community verdicts

Not labels, and they never appear on a product card. A verdict is an artefact: the question, the
method used to settle it, the answer, and the **minority view kept intact** — averaging the
dissent away would destroy the only part a reader cannot get from the table.

## A withdrawal has to say whether it is still in force

A computed label returns on its own when the data recovers, so the withdrawal list marks each
entry **Still withdrawn** or **Restored — the data recovered and the label was recomputed**.
Two of the five published withdrawals are currently restored. Without that state the page would
publish a withdrawal for a badge the shop is visibly wearing again.

Revocations are keyed by entity id, never by display name — the first version matched on a name
that was one word short of the record, and the page published a withdrawal for "IronLab" while
"IronLab Store" still showed the badge.

Files: `seed-labels.js` (catalogue, copy, editorial log, revocations, verdicts, brand lab
register), `labels.js` (`ComparoLabels(SEED)`: per-product, per-shop, per-brand resolution with
the missing criteria named, plus catalogue-wide coverage). Surfaces: `#/labels`, chips on every
product and shop page.
