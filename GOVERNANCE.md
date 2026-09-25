# Juries, the product wiki, and the returns index

Three mechanics, each closing a loop the rest of the product opened.

## Community juries — `#/juries`

Moderation had a queue and a moderator, which works until the decision is contested — and the
decisions most worth contesting are the ones where **we** have an interest: a review a paying
shop wants removed, a label we withdrew, a deal somebody called fake. Those go to a drawn panel.
We keep exactly one power, removing illegal content, and must log each use publicly within 24 h
with the legal basis.

* **Eligibility:** Expert level (900 XP), account older than 90 days, reversal rate under 15 %,
  no declared connection to either party, not drawn in the last 14 days. Each check shows your
  own current value beside it.
* **The draw is reproducible.** Seeded from the case id rather than `Math.random`, so the panel
  that decided a case last month can be recomputed and checked by anybody holding the data. A
  random panel nobody can verify is not a panel, it is an assertion.
* Nine seats, five votes decide, 72 hours, every vote carries a written reason, reasons publish
  without names. One appeal to a fresh panel of nine; the second verdict stands.
* Five case types, each with the standard the panel applies written out.
* **We publish our own overturn rate** — currently 33 % of appealed cases. A panel that never
  overturns anything is decoration.
* Service pays 120 XP into the same ledger everything else is paid from. It cannot be bought and
  is not visible to shops.

## Product wiki — `#/wiki`

A feed carries price, stock and a title. It does not carry the amount per serving, the allergen
line, the storage instruction, or a sentence explaining what the thing is for.

Seven fields, each with a **level gate and an evidence requirement shown before you type**:
plain-language summary (Regular, no evidence) → flavours (Contributor) → storage (Regular) →
allergens and certifications (Trusted, label photograph) → usage (Expert) → **dosing (Authority,
label photograph with the lot number legible)** — the highest protection on the site, because it
drives price per gram of active.

Proposals, not edits. Anything above Trusted needs two reviewers at or above the field's level.
Full version history with a diff per revision and a revert that is itself a revision. **Three
reverts on one field opens a jury case automatically** — there are no edit wars, only cases. A
shop may propose an edit to its own listing from a labelled account and it carries no extra
weight; a brand's specification sheet is recorded as a claim with a source until a photograph
agrees with it. Merged edits pay 55 XP and credit the contributor by revision.

## Returns index — `#/returns`

Delivery was measured and published; returns were not, which left the second half of a bad
purchase invisible. Computed from order records — reason, refund, dates — never declared.

* **It counts fault, not volume.** Wrong item and late are the shop's; damaged in transit counts
  half (the carrier shares it, the packaging does not); duplicate orders are not counted at all;
  changed-mind returns are not a fault and often mean the listing was accurate.
* **Two scoring regimes, because the samples differ.** Fault needs three returns before it means
  anything; below that a pair is scored on refund speed alone, which is measurable from the
  first return. Scoring everything "strong" because nothing went wrong twice tells a reader
  nothing and flatters every shop equally.
* Nothing at all is published below eight delivered orders in a market — the same threshold the
  delivery figures use. Currently 16 pairs published, 53 suppressed.
* Refund time is measured from the day the return was logged, not from the day the shop chose to
  process it. Who pays return postage is published as a fact, not scored.
* A merchant-side block names the four things a shop can actually do, three of them free — and
  points out that damaged-in-transit is the one return reason a shop can engineer away.

Files: `seed-governance.js`, `governance.js`.
