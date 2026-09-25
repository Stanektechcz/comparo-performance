# Visibility: what a shop can buy, and the wall in front of it

## Everything commercial is behind an approved shop profile

Advertising inventory, the promotion ladder's prices, add-on prices, plan prices, partner terms
and API pricing are all gated. Not as a lead-capture trick — **a price here genuinely does not
exist until we know four things**: the market, the surface, how many of that month's slots are
still free, and whether this shop passes that surface's quality gate. A public rate card would
be a fiction, and a fiction about price on a price-comparison site is a poor way to open.

**What stays public on the gate:** every rule, every cap, the full "what we will never build"
list, and the monthly sponsored click share. A shop can read all of it before giving us a field.

Four steps: sign in → create a shop profile (5 sections, ~15 min) → we review it (7 checks, 8
working days at the outside) → published, and the rate card opens. Listing, ranking, reviews,
delivery measurement and every one of the 27 markets are free and stay free.

## The application

Five sections — identity, catalogue, markets and delivery, policies, declarations — each stating
why it is asked for. Seven checks with SLAs and a stated failure mode: a failed check produces a
line-numbered report, not a rejection. Status machine: draft → submitted → in review → changes
requested → approved → published, plus suspended (offers suppressed in 24 h; reviews and history
stay published, because they belong to the record and not to the subscription).

## Nothing flashes

Forbidden and published as forbidden: animation, blinking, auto-play, interstitials, pop-ups,
sticky overlays, unverifiable scarcity language, imperative calls to action, position 1 of any
list, re-sorting organic rows, anything shaped like a trust label, retargeting pixels.

What is sold instead is **position and presence**, scored 1–5 for quiet:

| Format | Quiet | What it is |
|---|---|---|
| Shop desk hour | 1 | A staffed hour in a market room |
| Commercial marker | 2 | An outlined, disclosed chip under your shop name |
| Section announcement | 3 | One labelled post a month, repliable |
| Sponsored guide | 3 | You write it, we review the claims, it says sponsored |
| Spotlight / partner card | 4 | A card beside the content, with real feed data |
| Promoted row | 5 | The same row as an organic one, at a reserved index |

## A promoted row is inserted, never re-sorted

`assemble()` takes the organic list, walks the surface's reserved slot indices and inserts a
promoted row at each one it can fill. Every organic row keeps the rank its score earned — shown
beside its new position — and position 1 is untouchable. Twelve surfaces declare their reserved
indices publicly (deal hub 3 and 11 of 24, shop directory 4 of 14, search 4 of 20, and so on).

**The caps that bound it:** at most 2 promoted rows per rendered list, never two in the first
screen, at least 70 % of the fold organic, one slot per shop per surface per market, an
"organic order only" control on every list, and **20 % of every surface's capacity held back and
never sold** — that is how a new shop with a good price still gets discovered.

## Price is a calculation, and it is shown as one

Surface base × market weight (measured sessions; DE 1.0, SE 0.42) × scarcity (capped at +40 %,
because a runaway price would price out the shops the holdback protects), minus a data
completeness discount of up to −25 % and a commitment discount of up to −18 %.

The only discount we will not give is one for spending more: volume pricing rewards the shops
that least need help being seen.

Eligibility is separate from price. Eleven gates — verified tier, feed fresh, no upheld
reference-price flag, measured delivery, in stock, dosing declared, coupon works, relevance,
ships there, answers questions, claims checkable — and the rate card shows, per surface, which
ones this shop passes and what the failing ones currently read.

A promoted row that fails its gate **at render** is dropped and the slot refunded pro rata,
never filled with a fallback.

Files: `seed-visibility.js` (surfaces, slots, gates, formats, markers, caps, inventory,
application), `visibility.js` (`ComparoVisibility(SEED, labels)`: quality score, gate
evaluation, availability, price, assembly, booking). Surfaces: `#/shop-setup`, `#/visibility`,
and the gate that now fronts `#/advertising`, `#/promote`, `#/partners`, `#/developers`,
`#/for-merchants/pricing` and `#/for-merchants/addons`.
