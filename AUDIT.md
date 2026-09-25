# Prototype audit — what is missing, unconnected or worth improving

Written after the navigation, account-menu, verification and homepage work. Ordered by severity, not
by module. Every item names where it lives so it can be picked up directly.

---

## J. This round: live rooms, contribution engine, 27 markets, guarantee, paid extensions

Five layers added, each with its own document (LIVE-ROOMS, CONTRIBUTIONS, DELIVERY-GUARANTEE,
PAID-ADDONS, DELIVERY-MARKETS). What is worth recording here is the defects found and fixed while
building them, because they are all instances of the classes this file already tracks.

* **The promise every shop missed.** `seed-orders.js` set `promisedDays = zone.days[1]` — the
  *transit* upper bound — and then measured `actualDays` from order to door, dispatch included.
  Every shop therefore showed an on-time share between 14 % and 38 %, which is not a plausible
  figure for any working e-commerce operation and made a delivery guarantee arithmetically
  impossible. Promised days are now `zone.days[1] + 1`; dispatch and transit were tightened; and
  a thin tail of real carrier failure (≈1 order in 30) was added, because a dataset with *no*
  late deliveries makes both the guarantee and the p90 meaningless. Measured on-time is now
  92–100 % in strong shop–market pairs and much lower in weak ones.
* **Enrolment derived from the invoice was the wrong direction.** The first version read
  `cx.merchantAddons` and enrolled whoever had paid. That allows both failure modes of this
  codebase's favourite defect: a badge nobody is billed for, and a bill for a badge never earned.
  Enrolment is now computed from the measurement and the billed line is generated from the
  enrolment. A shop that wanted the 48-hour tier without the p90 to support it is published at
  the window it earned, with `downgraded: true` on the record.
* **A missing forum category.** A seeded thread used `category: 'price-history'`, which had no
  record — a topic whose category does not exist renders a breadcrumb to nowhere and a null
  category header. Declared in `seed-live.js`.
* **AUDIT § I.2 closed.** Placement audience figures were strings on the placement records
  (`'268k monthly sessions'`). They are now computed from measured entrances per page type and
  the share reaching each slot, and each row prints its own derivation.
* **AUDIT § I.3 closed.** The feed interval is now an entitlement (`feed.interval_hours`) with a
  plan floor and an add-on that lowers it, so merchant copy interpolates the number instead of
  asserting "imported every six hours" in prose.
* **A revenue note that outran its data.** `revenueMixNote` claimed buyer subscriptions were
  "deliberately the smallest"; with 41 members × a 620 multiplier against 13 shops they are the
  largest. The note now states the principle (nothing on the list buys ranking, review weight or
  a badge) and explains the ordering instead of asserting one.

Still open from § I: Growth OS and Commercial OS scenario constants (I.1), and the
trust-centre/methodology prose that quotes weights in words rather than reading `weights()` (I.4).

New guard for this round: **a paid record and a measured record must never both be sources of
truth for the same statement.** Decide which one is derived, and derive it in code.

## M. This round: juries, the product wiki, the returns index

Three mechanics with complete logic rather than three surfaces. See GOVERNANCE.md. Two decisions
worth recording here:

* **The jury draw is seeded from the case id.** A panel drawn with `Math.random` cannot be
  checked after the fact, which makes "nine members were drawn" an assertion rather than a
  record. Same id, same pool, same nine names, forever.
* **The returns index nearly shipped uninformative.** The first verdict rule scored on fault
  alone; with 21 returns across 69 shop–market pairs almost every published pair had a fault
  rate of zero and read "strong" — technically true, and useless. Fault is now scored only at
  three or more returns, and thinner pairs are scored on refund speed, which is measurable from
  the first return. The spread went from 16 identical verdicts to 2 fast / 5 ordinary / 2 slow /
  7 with no returns yet.

Caught by review in the same round, both the same defect class in different clothes:

* **The panel size contradicted itself in five places.** `draw()` clamps to the eligible pool,
  which holds seven members, while the rule text, `tally()`'s hardcoded `seats: 9` and the
  seeded vote totals all asserted nine — so every card read "8 of 9 seats voted · 5 decides"
  above a panel of seven chips, on the page whose argument is that the draw is auditable. Seats
  now come from the drawn panel: `tally(c, myVote, panel.length)` scales the seeded tallies to
  fit, quorum is a majority of the seated panel, the seat sentence is interpolated ("the pool
  holds 7, so a panel seats 7 and 4 decide"), and the duplicate `panel` array on each case
  record is deleted — two panels of record is the same bug waiting to recur.
* **`tenureDays: 140` was typed and rendered as a measurement**, printing "Account older than
  90 days — 140 days" for every visitor including signed-out ones. It reads `meUser().joined`
  now, so the demo account correctly shows "3 days" and correctly fails the check. Same defect
  as the fabricated reply latency two rounds ago, on a page that argues seats are allocated on
  checkable criteria.

And the fix for the first of those introduced one of its own, caught the round after:

* **A cast vote was silently discarded.** The scaling filled the panel to exactly `seats`, so
  the reader's vote never fitted — while `juryVote()` still paid 120 XP, wrote an audit entry
  and toasted "Voted to overturn". On an open case the seeded tally is now scaled to leave one
  seat free, because a member who takes a seat has to occupy one. Verified: 2/1/0 cast 3 →
  2/2/0 cast 4, leading flips to tied, free seats 4 → 3.
* **Every open case was past its own deadline and most were already decisive.** `opened` was
  drawn up to 40 days back against a 3-day deadline, so all six cases read "closed" while
  rendering vote buttons, and two had already reached quorum with no verdict — a page arguing
  for a 72-hour process that was visibly not following it. Open cases are now seeded inside the
  window (54 h, 30 h, 31 h left) and short of quorum (3 of 7 cast, 4 decides), so an open case
  is one a vote can still decide; decided cases are full panels with a verdict.

* **The jury vote was unreachable from the UI.** Both previous fixes were proven by calling the
  engine directly; through the page every click was refused, because `meUser()` hardcoded
  `joined: now() − 3 days` and the catalogue clock only advances by session time — so the
  90-day tenure criterion was structurally unsatisfiable for every role, and `juryVote()` was
  dead code behind a gate that could never open while nine live buttons invited the click. The
  join date is an arbitrary constant either way; it is now 214 days back, which makes every
  tenure rule on the site checkable and leaves the criterion that must be *earned* — XP — where
  it was. The three vote buttons also render flat, with the blocking criterion printed beside
  them, so an action that will be refused is no longer offered. Verified end to end through the
  UI: eligibility reads "214 days ✓" and "315 XP ✕" with "Seat blocked: expert level or above",
  one click on the contributions page moves 315 → 340, and at 1,015 XP a click on Overturn takes
  the card from "3 of 7 seats voted" to "4 of 7", renders "You voted to overturn" and persists.

  **Rule:** a criterion that no user action can ever satisfy is a broken gate, not a strict one.
  Proving a fix by calling the engine is not proving the feature — click the button.

Both XP sources these mechanics create (jury service, wiki edit merged) were pushed into the
existing ledger rather than given their own counter — a parallel points system would be a second
truth about the same member.

---

## L. This round: the commercial wall, and promotion that moves position

* **Every price moved behind an approved shop profile.** Six routes now render a gate for
  anyone without one. The gate is not a teaser: it carries the caps, the full refusal list and
  the monthly sponsored click share, because those are exactly what a shop needs before it
  decides to spend fifteen minutes on a form. Implemented as a last-pass flag switch in
  `renderVals` so it can turn off flags set earlier in the same function — the first attempt
  set them before the flag assignments and was silently overridden.
* **Promotion now moves position, without touching the ranking.** The earlier model said paid
  placement never affects order, which was true and also not what a shop wants to buy.
  `assemble()` resolves it honestly: a promoted row is **inserted** at a reserved slot index,
  every organic row keeps the rank its score earned, and position 1 is not for sale on any
  surface. Twelve surfaces publish their reserved indices; 20 % of capacity is held back unsold.
* **Availability is live and price is a calculation.** Surface base × market weight × scarcity
  (capped at +40 %), minus a data-completeness discount. The rate card shows free slots per
  surface per market, the holdback, and per-gate eligibility for the reading shop.
* **Relative time was read off the wrong clock, in four places.** This prototype runs two
  clocks: the catalogue's NOW, which every seeded record is dated against, and the real one,
  which stamps anything the reader just did. `helpers.ago()` measures against the first, so a
  real-clock timestamp put through it prints a constant 206-hour offset — a booking made a
  second ago read "1 h ago", and so did one made five minutes ago. Fixed once at the
  application's submitted stamp, then found still live at three siblings written in the same
  edits: the visibility booking list, the buyer tier log and the delivery-claim list. All four
  now go through one extracted `agoReal()`; the seeded ledgers keep `helpers.ago` because they
  are stamped with the catalogue clock.

  **Then fixed properly, because the rule was the wrong fix.** Patching named call sites left
  the largest instance live: `audit()` stamped `Date.now()` and the audit log rendered it with
  `helpers.ago`, so every entry a reader generated read "1 h ago" — on the one surface whose
  entire purpose is that its timestamps can be read. Two causes, both removed:

  1. `helpers.ago()` measured against the **frozen** `S.NOW` and floored at one hour
     (`Math.max(1, …)`), so *any* fresh record printed "1 h ago" whichever clock stamped it.
     It now reads a live catalogue clock and resolves to "just now" / minutes / hours / days.
  2. **One clock.** All 58 timestamp fields the app writes were switched from `Date.now()` to
     `this.now()`, and `agoReal()` was deleted along with its four call sites. `Date.now()`
     now appears only in id generation, boot timing and keyboard chords — never on a record.
     Two comparisons that read a record against the wrong clock went with it (report ageing in
     `confidenceOf`, the newsletter calendar fallback).

  **Third pass, and the actual root cause: the clock had two anchors and neither survived a
  reload.** The formatter anchored at script-parse time; the component anchored lazily, on the
  first call to `now()`. A record stamped by the second and read against the first was old by
  exactly the lag between the two samples — sixty seconds if nothing had called `now()` yet,
  which is why a tier switch 1.3 s old printed "1 min ago", and why landing on a page with a
  countdown made it look fixed. And because both restarted at `S.NOW` on every load, stamps
  persisted from an earlier session landed in the *future* and printed "just now" above
  genuinely newer rows in the audit log.

  One anchor now, defined beside the formatter in `seed-visibility.js`; the component's
  `now()` delegates to it. The elapsed offset is persisted every five seconds and on
  `pagehide`, so the catalogue clock is monotonic across sessions. On load the anchor is also
  advanced past the newest stamp already in storage, so records written under the old regime —
  or after a lost offset — can never sit in the future. Verified after a reload: zero stored
  stamps ahead of the clock, a fresh action reads "just now", the oldest notification reads
  "10 days ago".

  **Rule:** one clock, one anchor, persisted. A record is stamped with `this.now()` and
  rendered with `helpers.ago()`, and both resolve to the same anchor. Three rounds were spent
  fixing call sites, a formatter and finally the anchor — the lesson is that a symptom
  reachable by several roads has a cause upstream of all of them.

  Also found while sweeping: `plLog` and `gpMine[].when` were computed and never rendered —
  two of the three "fixes" from the previous round were dead view values. Both are on screen now.

Guard added: **a commercial number must be computed for a known shop, or not shown.** If the
answer depends on market, availability and eligibility, publishing a single figure is a guess
wearing a price tag.

---

## K. This round: labels, the promotion ladder, the partner network, demand signals

* **Chat moved beside the archive, not above it.** The section room was a full-width block ahead
  of the topic list, which put a transient surface in front of the durable one. It is now a
  ~4/12 rail beside the list (flex `1 1 300px` against `1 1 520px`, so it reflows rather than
  relying on a breakpoint).
* **Labels were the missing trust primitive.** Eight computed labels, each naming its records,
  its criteria, what it does *not* mean, and its revocation rule; plus the four we refused and
  why. `labels.js` also returns the **missing** criteria, so a shop can see the distance to a
  label without anybody being able to buy the distance away. See LABELS.md.
* **Promotion had a price list but no ladder.** Eleven rungs ordered by intrusion rather than by
  price, with the first six free or under €100 and explicitly the ones we push. The
  never-for-sale list is now stated in the same place a shop is deciding what to buy, which is
  where it is worth something. See PROMOTION.md.
* **"Affiliate" was one field on a merchant.** Now six networks with cookie windows, validation
  delays, dedup policy and payment terms; commission by category with volume tiers; ten partner
  kinds, four tiers, a seven-stage pipeline with exit conditions, and five processes with SLAs
  and owners. See PARTNER-NETWORK.md.
* **Demand signals are new, and they are the only forward-looking signal in the product.**
  Everything else we hold describes the past. See DEMAND-SIGNALS.md.

Caught by review in the same round:

* **The room panel was authored for full width and broke in the rail.** Mounted at ~4/12 on
  every forum section, its message row held a fixed 74 px track for three icon buttons, so with
  the avatar and gaps the text column was narrower than the chrome around it — every message
  wrapped one sentence onto five lines. The action cluster moved onto the meta line with
  `margin-left:auto`, and the row became flex with a `min-width:0` body. Bodies now measure
  464 px at preview width and run one to two lines, and the same panel still reads correctly at
  full width on `#/live/<room>`.

And, both in the evidence behind a public badge:

* **"Honest reference prices" read a field that does not exist.** The check filtered offers on
  `o.fakeDiscount`, which is not a key on any offer record, so the label was unconditional —
  13 of 13 shops — while the withdrawal list published a reference-price withdrawal for one of
  them. It now runs a real check: a struck-through price above anything the market ever charged
  for that product (the brand RRP, and the shop's own 90-day high where we hold the series).
  2 of 13 shops hold it.
* **"Answers fast" printed a fabricated latency under a line claiming it was measured.** Reply
  hours were synthesised from the review's primary key. Reply timestamps existed but were drawn
  independently of the review, giving gaps of 46 to 184 days, so the real figure was unusable
  too. Both halves are fixed at the right layer: `seed-labels.js` now derives each reply
  timestamp from its review (4–96 h) and gives partner shops a realistic answer rate, and
  `labels.js` measures `reply.date − review.date`. 3 of 13 shops hold it.
* **Two labels were unearnable on the seeded sample** (8 reviews, 10 verified reviews) and showed
  0 of 46. Re-pitched to 5 and 4, with the criteria copy on the page changed in the same commit
  so the published criteria and the code cannot disagree.
* **Revocations were keyed by display name**, and one name was a word short of the record, so a
  withdrawal was published against a shop that still wore the badge. Keyed by id now, and each
  withdrawal states whether it is still in force or has been restored by recomputation.

Guard added: **a label must publish what it does not mean.** Any future badge that cannot state
its own exclusion does not ship — that test is what removed "Trusted shop", "Best price",
"Official partner" and "Editor's choice".

---

## A. Broken or half-built (fix first)

| # | Issue | Where | Why it matters |
|---|---|---|---|
| A1 | ~~Forum has no reply composer~~ — **wrong, it works.** Verified: posting a reply persists to `state.replies`. | `#/forum/topic/…` | Corrected after testing. |
| A2 | ~~Community hub is read-only~~ — **wrong, it works.** Verified end to end: "Start a discussion" → topic created, persisted to `state.threads`, redirect to the new thread. "Share a deal" and "Write a guide" use the same modal set. | `#/community` | Corrected after testing. |
| A3 | **FIXED THIS ROUND — two overlays rendered at once.** `modalOpen` was true for every modal type, so any "extra" modal (thread, deal, guide, report, list, why, trust, whyrank, reportoffer) also rendered an *empty* primary dialog on top of it — two `role="dialog"` nodes, the upper one containing nothing but a Close button. This is what made the community compose buttons look dead. The two overlays are now mutually exclusive. | modal builder | Was intercepting the real modal and breaking screen-reader semantics. |
| A4 | **Public profile is thin.** `#/users/<handle>` shows activity and badges but not reputation breakdown, review credibility, or a follow button. | `#/users/…` | Reputation is a documented system with no public surface. |
| A5 | **Merchant Q&A is display-only.** Shop pages show a Q&A tab; there is no ask/answer control. | `#/shops/…` | Referenced by the lead system (`LD-3` is a "Merchant Q&A" lead) with no way to generate one. |
| A6 | **`state.mcBillingTab` and `mcInvoicesArchive` are dead.** Left from the commercial iteration. | merchant builder | Dead state misleads the next reader. |

---

## B. Not connected across the prototype

| # | Gap | Detail |
|---|---|---|
| B1 | **CLOSED.** Admin → Moderation → **Purchase proofs** queue: each pending proof shows its route, target, age and the specific checks a moderator must make (sender domain, order date, total against that shop's prices that week), with Confirm / Cannot verify. Verified end to end: tab counted (1), confirming emptied it to (0). | Loop closed. |
| B2 | **CLOSED.** `reviewCard()` now consults `proofFor()` first: a confirmed click match reads "Verified — click matched", a pending one reads "Verification pending" in amber, and only then does it fall back to the seeded flag. | Label always matches the evidence held. |
| B3 | **CLOSED, with a stated limit.** `ratingOf()` weights each review by `reviewWeight()`: verified purchase 1.0, normal 0.75, needs review 0.6, suspicious 0.25. Deliberately gentle — a suspicious review is quietened, never deleted, and stays visible with its label. **Product ratings** are computed wholly from weighted records. **Shop ratings** blend: the seeded `m.rating` is a *population* average over `m.reviews` (PeakSupps: 4.8 over 3,421) while the prototype holds only a sample of actual records (13), so the weighted sample moves the published figure in proportion to the share it represents — with 13/3,421 that movement rounds away, which is correct rather than inert. The shop profile now states both numbers separately ("Reviews 3,421" / "On Comparo 13 weighted") so the distinction is visible instead of hidden. A real deployment holds every record and the blend collapses to a straight weighted average. | The documented claim is now true, and its scope is stated. |
| B4 | **CLOSED.** `confidenceOf(o)` wraps `priceConfidence()` and applies open shopper reports: confirmations lift (+7), a changed price (−15), out of stock (−11) and a failed coupon (−5) lower, capped at +12 / −34, and reports older than a fortnight count half. The report toast quotes the movement using the *same function* that renders the badge, so the promise cannot contradict the panel. | Loop closed; intel.js stays feed-only and pure. |
| B5 | **Basket compare does not use ComparoRank.** It optimises on total price and shipping only; trust is offered as a separate "highest-trust" option rather than a rank-weighted one. | Inconsistent with "organic ordering comes from ComparoRank". |
| B6 | **Growth tasks and commercial tasks are one list with two vocabularies.** `addGrowthTask()` serves both; commercial task types are growth types. | Fine for a prototype, but the docs describe distinct sources. |
| B7 | **Creator coupons are not real coupons.** Issuing one toasts and audits; it does not appear in `allCoupons()`, so a creator code never works on a deal page. | Creator programme cannot be walked end to end. |
| B8 | **Alerts never fire.** Alert records persist and the digest lists them, but nothing evaluates them against price movement to produce a triggered notification. | "3 price alerts triggered" in the action centre is seeded, not derived. |
| B9 | **Language switcher changes nothing.** It persists `state.lang` and toasts; no string is translated and the `hreflang` machinery is unaffected. | Honest as a control, but currently decorative. |
| B10 | **CLOSED.** All three built in `buildPublicCommercial(kind)`. Pricing renders `cx.plans` + `cm.planMatrix()` with a month/year toggle and names its own "most chosen" plan from live subscription counts; developers renders `cx.apiPlans` + `cx.dataProducts`; advertising renders `cx.placements` with live booked/capacity per slot and a real running campaign as the example unit. Linked from nav and footer. | Every figure reads the record the invoices are written against. |

---

## C. Data-model gaps

* ~~**No order entity.**~~ **CLOSED** — `seed-orders.js` builds one order per verified-purchase review plus unreviewed purchases per shop. Delivery medians, p90, on-time share, return and dispute rates are measured from these rows per market, and suppressed entirely below eight delivered orders rather than published on a sample of three. Review verification labels now name their order. See ORDERS.md.
* **No per-review shop/product cross-link.** A shop review cannot reference the product it was about, so "the whey arrived crushed" cannot surface on the product page.
* ~~**Ingredients are not dosed.**~~ **CLOSED** — `seed-dose.js` holds mg per serving for all 46 products, with source and read date. Price per gram of active is now a headline on the product page, a row in compare, and a full ranking on every ingredient page ("what a gram of creatine costs": a 10× spread for the same molecule). Carriers are dosed but excluded from the active total. Market dose limits are read against the amount rather than stored beside it. See DOSING.md.
* **Price history is per product, not per offer.** Merchant-level history exists (`hist.byMerchant`) but only for a subset, so per-merchant fake-discount detection rests on partial data.
* **No stock history.** Stock confidence is computed from feed freshness alone; an offer that flickers in and out looks identical to a stable one.

---

## D. Worth improving (highest value first)

1. **Close the verification loop** (B1 + B2 + B3). Add a proof tab to moderation, read `proofFor()` in `reviewCard()`, and weight the public average by review trust. This is the single most credibility-relevant change available — and the click-match route is genuinely defensible, because no competitor holds our redirect log.
2. **Make the forum and community hub writable** (A1–A3). Three composers — reply, new topic, guide — turn three read-only modules into the contribution engine the rest of the product assumes.
3. **Dose the ingredients.** Amount per serving per product unlocks price per gram of active ingredient, which is the comparison no other price site in this category can do, and it feeds Ask Comparo directly.
4. **Introduce an `Order` entity** even in prototype form. It gives verification, delivery reliability, disputes and returns a single anchor, and removes three seeded figures.
5. **Evaluate alerts for real** (B8). A deterministic pass over price history against each alert's target would make the notification centre, the digest and the "action required" strips all derived rather than seeded.
6. **Per-offer price history** for every offer, not a subset — fake-discount detection and merchant price-accuracy scoring both currently reason from partial series.
7. **Make the language switcher real** for at least the shell (nav, footer, buttons). Partial translation with an honest "product data stays in source language" note beats a decorative control.
8. **Reduce seeded analytics.** Roughly a third of the numbers in Growth OS and Commercial OS are seed constants rather than derived (channel users, cohort retention, page-type revenue, assisted conversions). Each one is a future contradiction — the last several rounds of review were entirely about figures that drifted from their records. Anything displayed should be computed from an entity or clearly labelled as a scenario input.

---

## E′. The canonical-graph seam (fixed)

**Listings rendered merged records as separate products.** `buildProduct()` resolved a merged
record to its survivor via `mergedInto()`, but every *listing* builder filtered `S.products`
directly — so one real product rendered as two cards with two different prices, both linking to the
same page, and the header count was inflated to match. Worst instance: `/brands/ironforge` showed
"Whey Hydro Peptides · 36,13 € · 7 shops" beside "Whey Hydro Peptides · 32,49 € · 5 shops".

Fixed with **`canonicalProducts(list)`**, the listing counterpart to `publicRows()`: resolves each
record through `mergedInto()` and de-duplicates by canonical id. Applied to the product index,
brand pages, category index and detail, ingredient pages, search, trending and related products.
Counts are taken from the de-duplicated set, so a header can no longer contradict the cards below it.

Result: brand 5 → 4 cards, product index 46 → 45 with 18 duplicates gone, category and ingredient
listings clean. Same product appearing in a card grid *and* a sidebar module is left alone — that is
two modules, not a duplicated listing.

**Counts are the same seam as lists.** A second pass found the purest instance of this defect: a
homepage stat literally labelled "Canonical products" read `S.products.length` and printed 46 while
the catalogue it linked to rendered 45. Ten more raw counters had the same fault — the admin
dashboard, the country hub, price-snapshot totals in two places, the Ask Comparo insight sentence,
the entity-graph combination maths, and a mean over base prices that would have double-weighted the
merged record. All now route through the seam; `S.products.length` no longer appears in the file.

The rule for anything added later: **a list of products goes through `canonicalProducts()`, a
*count* of products goes through `canonicalProducts(...).length`, and a list of offers goes through
`publicRows()`.** None is optional, and they exist because the canonical graph is only a moat if
every surface honours it — a stat that names the graph and then bypasses it is worse than no stat.

## E. Consistency risks already fixed, worth guarding

The recurring defect class in this prototype has been **a display string written next to the record it
describes**. Fixed so far: campaign names, past-due alerts, API allowances, invoice overage lines,
credit-note reasons, plan catalogues (merchant vs staff), invoice ledgers (merchant vs finance),
renewal days, campaign pacing, revenue concentration, and market links in the nav.

The guard is simple and should be applied to anything added later: **if a number or a name appears in
copy, interpolate it from the entity — never type it.** Alerts are now a computed view
(`cm.alerts()`), campaign labels derive from `merchantId`, and nav market links derive from
`S.countries`. New code should follow the same rule.

---

## G. Search relevance (fixed)

**Every query returned the whole catalogue.** `searchAll()` computed a correct relevance score and
then discarded it: `synHit` was a *query-level* boolean — "does the query contain any synonym
token" — applied as a per-product score floor of 34. So `melatonin` (in the `sleep` group) lifted
all 45 products past the `sc > 0` cutoff, and the Products tab read "(45)", numerically identical
to the catalogue total. Ranking was never wrong; only the cutoff was missing.

Fixed by making the synonym expansion **per-product**: the query's synonym groups contribute their
*sibling* terms, and a product is lifted only if its own name, ingredients or category matches one
of them. `melatonin` 46 → 6 results (Melatonin Sleep, then ZMA and the recovery blends via the
sleep group), `kreatin` → 9 with the three creatine products first, `vitamin` → 11.

Rule: **a synonym expands the query, it does not raise the floor.** Any per-query boolean applied
inside a per-item loop is the same bug waiting to happen.

## F. Smaller polish

* Product cards have no imagery anywhere — `image-slot`-style placeholders would make the grids read as a shop rather than a table. **Still open; needs real product photography to be worth doing.**
* No skeletons on route change; the design system documents them but only the design-system page shows them. The loading indicator itself is now an inline SVG (design system + redirect page) rather than a glyph, so it cannot flicker or reflow while the webfont loads.
* ~~Search has no recent-searches or zero-result suggestion UI~~ — **done.** Focusing an empty search box lists the last six searches with their result counts (zero-result ones in red) and a Clear control. A zero-result page now derives "did you mean" from edit distance across products, brands, shops and ingredients, lists the searches that *did* find something, offers category browsing, and states that market restrictions exclude some products.
* Compare page allows four products but has no "add from search" affordance inside the compare view.
* ~~Deal cards show expiry text but no countdown~~ — **done.** `countdown(ts)` returns a live label that escalates minutes → hours → days and colours urgent states. The catalogue clock (`now()`) starts at the seed NOW and advances in real time from page load, so a countdown agrees with every other seeded date instead of being a week out. A 30-second tick runs only on routes that show expiry.
* ~~Mobile: the mega panels are tall~~ — **done.** When the bottom bar is on screen, `boundNavPanel()` marks the panel `data-sheet` and it becomes a real bottom sheet: fixed to the bottom, rounded top, grab handle, capped at 72 vh, padded clear of the bar. Its height no longer depends on how far the page happens to be scrolled.
* ~~Print styles exist for comparisons but not for invoices~~ — **done.** `[data-print-doc]` starts a new page, drops screen chrome and forces black on white; `[data-print-only]` blocks add the issuer identity, VAT and registration, the billed-to party, and a subtotal / tax / total block with payment terms — none of which a screen invoice row needs but a printed one is worthless without.


---

## H. Responsive bailout on the narrowest devices (fixed)

Every card grid in the app is `repeat(auto-fit, minmax(Npx, 1fr))`. Below roughly 430 px that
`minmax` floor is wider than the viewport, so the grid correctly collapses to one column and
then the cards overflow it horizontally. Adding a class to several hundred inline-styled grids
was not an option, so the bailout selects on the inline style itself:

```css
@media (max-width:430px){
  [style*="repeat(auto-fit"],[style*="repeat(auto-fill"]{grid-template-columns:1fr !important}
}
```

Ugly, and correct: it is one rule, it cannot go stale as grids are added, and it only fires on
viewports where the floor is already unreachable.

---

## I. Still typed rather than derived (the remaining defect class)

Fixed this round: the SEO console's "Product → Comparison" and "Guide → Entity" link counts
(were `'14'` and `'26'`, now counted from the related-product graph and the guides' own
`relatedProducts`); the merchant landing page's conversion rate and commission range (were
`'4.1 %'` and `'4–12 %'`, now the median `cvr` across affiliate metrics and the true min–max of
merchant commission agreements).

Still outstanding, in rough order of how likely each is to be caught contradicting itself:

1. **Growth OS and Commercial OS scenario constants** — channel users, cohort retention,
   page-type revenue and assisted conversions are seed constants presented as measurements.
   Each should either derive from an entity or be visibly labelled a scenario input.
2. **Placement audience figures** (`'268k monthly sessions'`) are strings on the placement
   records. They are at least *on the record* rather than in copy, but nothing computes them.
3. **Feed SLA prose** — "imported every six hours" is written into the merchant landing copy.
   The interval is not held anywhere as a number, so there is nothing to interpolate from yet.
4. **Trust-centre and methodology prose** quotes weights in words. The weights exist in
   `weights()`; the sentences do not read them.
