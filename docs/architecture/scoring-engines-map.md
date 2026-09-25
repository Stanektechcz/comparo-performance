# Scoring engines map (prototype → Laravel parity)

Scope: where every scoring algorithm of the "Comparo Performance" browser prototype really lives,
what it reads, how it computes, and how to export golden fixtures headless for the Laravel 13 /
PHP 8.4 port. All line numbers refer to the files as of 2026-09-25.

Abbreviations: **HTML** = `Comparo Performance.dc.html` (20 494 lines; the app logic is one
`class Component extends DCLogic`, lines **11679–20491**). **ix** = the object returned by
`window.ComparoIntel(SEED)` (`intel.js`). `S` = `window.SEED`.

Verification status: everything marked *verified* was checked by reading the code and, for the
offer pipeline, by running the headless recipe in the last section under Node 24.16 (two runs,
identical SHA-256 over 1 068 offer rows × 4 markets). Items marked **unverified** were inferred from
reading only.

---

## 0. Cross-cutting facts

### 0.1 Clock

| Symbol | Value | Where |
|---|---|---|
| `S.NOW` | `new Date('2026-09-06T09:00:00Z').getTime()` = **1788685200000** | `seed.js:13` |
| `S.DAY` | `86400000` | `seed.js:12` |
| `HOUR` | `3600000` (local const) | `intel.js:6` |
| `S.helpers.now()` | live clock: `S.NOW + offset + (Date.now() - BOOT)`; `offset` read from `localStorage['comparo.clock.v2']` (0 if absent, reset if > 30 days) | `seed-visibility.js:48-61` |
| `Component.now()` | `S.helpers.now()` if present, else `S.NOW`, else `Date.now()` | HTML 17947 |

Every scoring function in `intel.js` uses the **frozen `S.NOW`**. The HTML uses `S.NOW` in the offer
pipeline (coupon expiry, freshness) and the live `this.now()` only for shopper-report ageing in
`confidenceOf` (HTML 13239) and for stamping new records. With `Date` frozen at `S.NOW` and an empty
`localStorage`, `helpers.now() === S.NOW` (verified).

### 0.2 Determinism of the seed

All seed files generate data with a local mulberry32-style PRNG (`Math.imul` based), never
`Math.random`:

| File | Seed |
|---|---|
| `seed.js:4-5` | `mk(20260906)` |
| `seed-intel.js:7-8` | `mk(20260907)` |
| `seed-geo.js:23` | `0x3f1a7c5d` |
| `seed-orders.js:16` | `0x9e3779b9` |
| `seed-visibility.js:28` | `0x41c9e7b3` |
| others (`seed-community`, `seed-growth`, `seed-seo`, `seed-commercial`, `seed-addons`, `seed-gamify`, `seed-labels`, `seed-live`, `seed-network`, `seed-governance`) | own `mk(...)`/`R()` (not individually listed) |

Seed files **mutate earlier seed objects in place** (load order matters and must be reproduced):

* `seed-community.js` pushes merchants 9–13, products 29–46, offers, coupons, reviews, users,
  compliance rules, and adds `responseRate / verifiedOrderRate / complaints30 / complaintsResolved`
  to every merchant (`:81-112`).
* `seed-geo.js` adds 15 countries (12 → **27** markets; the "12 markets" in the brief is the
  `seed.js` baseline only), extra currencies, and **extends `m.shipsTo` / `m.zones`** of every
  merchant (`:85-128`). Aliasing trap: coupons created with `countries: m.shipsTo` (the Comparo
  exclusives, `seed.js:190`, and all `seed-community.js:137` coupons) share the array reference and
  therefore silently gain the new markets; coupons created with `m.shipsTo.slice(...)`
  (`seed.js:178`) do not.
* `seed-dose.js` sets `p.doses`, `p.doseSource*`; `seed-orders.js` builds `S.orders` and rewrites
  `review.verifyMethod`.
* `seed-intel.js` (loaded late) sets `m.ix`, `o.ix`, `c.ix`, **overwrites 4 offer prices** to
  create anomalies (`:174-192`), makes 4 offers stale (`:194-195`), raises 3 reference prices
  (`:215-223`), forces coupon states (`:227-249`), and sets `S.ix.rankWeights` (`:544`).

Consequence for Laravel: **do not re-implement the seed generators**. Export the fully
materialised `SEED` after the whole load order as the fixture input (see §13).

Verified counts after full load: 27 countries, 13 merchants, 46 products, 267 offers, 28 coupons,
408 orders.

### 0.3 Numeric helpers (all engines)

```js
const clamp = (v, a, b) => Math.max(a, Math.min(b, v));          // intel.js:9
const r1 = (v) => Math.round(v * 10) / 10;                        // intel.js:10
const r2 = (v) => Math.round(v * 100) / 100;                      // intel.js:11
const med = (arr) => { if (!arr.length) return 0; const s = arr.slice().sort((a, b) => a - b); return s[Math.floor(s.length / 2)]; }; // intel.js:12 — UPPER middle, no averaging
const avg = (arr) => (arr.length ? arr.reduce((a, b) => a + b, 0) / arr.length : 0);             // intel.js:13
```

PHP parity notes: `Math.round` is round-half-**up toward +∞** (`Math.round(-2.5) === -2`), unlike
PHP `round()` (half away from zero). Use `floor($x + 0.5)` for negative-capable values (deal score
parts, penalties). Many defaults use JS `||`, so **0, NaN, '' and null all fall back to the
default** (e.g. `ctx.freshnessHours || 12`); PHP must use a falsy check, not `??`.

There are three different medians in the codebase: `intel.med` (upper middle), HTML
`deliveryStats` (averaged middle, HTML 16879) and `seed-addons.js:27` (averaged, then `r2`).

---

## 1. ComparoRank (offer ranking)

### 1.1 Location

| Piece | File:lines | Role |
|---|---|---|
| `rank(ctx)` | `intel.js:562-609` | pure scorer |
| `S.ix.rankWeights` | `seed-intel.js:544` | `{ price: 30, trust: 20, delivery: 14, reviews: 12, freshness: 10, availability: 8, shipping: 6 }` |
| `weights()` | HTML 13101 | `Object.assign({}, S.ix.rankWeights, state.rankW || {})` (Ranking Lab override) |
| `marketStats(productId)` | HTML 13102-13123 | market min total / medians per product × country |
| `enrichRow(row, o, m, p, coupon)` | HTML 13124-13169 | **builds ctx**, calls `ix.rank`, copies result to the row |
| `offerRow(o)` | HTML 12928-12985 | price → coupon → shipping → total, then `enrichRow` |
| `sortRows(rows, mode)` | HTML 13284-13301 | ordering; default `state.sort = 'rank'` (HTML 11693) |
| Why-this-rank modal data | HTML 20441-20454 (`wrData`, `wrWeights`), template 11594-11617 |
| Ranking Lab | HTML 15079-15092 (product id 6, sliders 0–40) |

### 1.2 Exact ctx built by the host (HTML 13132-13142)

```js
ix.rank({
  total: row.totalNum, marketMin: mk.min || row.totalNum, marketMedian: mk.median,
  ship: row.shipNum, shipMedian: mk.shipMedian, deliveryDays: row.deliveryNum,
  rating: row.ratingNum, reviewCount: m.reviews, trust: t.score,
  freshnessHours: (this.S.NOW - o.updated) / 3600000, availability: o.availability,
  hasValidCoupon: !!(cm && cm.usable), completeness: cpl.pct / 100,
  anomaly: !!(o.ix && o.ix.anomaly) || o.price === 0, fakeDiscount: !!fake,
  linkFlag: !!(o.ix && o.ix.link), complianceUnknown: comp.status === 'unknown',
  complianceBlocked: comp.status === 'not_allowed' || comp.status === 'prescription_only',
  riskLevel: rk.level, weights: this.weights(),
});
```

Source of each input:

| ctx field | Derived from |
|---|---|
| `total`, `ship`, `deliveryNum` | `offerRow` (§2): `totalNum`, `shipNum`, `zone.days[1]` (99 if not shipping) |
| `marketMin`, `shipMedian` | `marketStats` (below) |
| `rating` | `ratingOf('merchant', m.id).avg` (credibility-weighted blend, §8) — falls back to `m.rating` |
| `reviewCount` | `m.reviews` (seeded population count) |
| `trust` | `ix.trust(m).score` (§4) |
| `freshnessHours` | `(S.NOW - o.updated) / 3600000` |
| `availability` | `o.availability` |
| `hasValidCoupon` | `couponMeta(bestCoupon).usable` (§2) |
| `completeness` | `ix.completion(p.id).pct / 100` (`intel.js:327-336`) |
| `anomaly` | `o.ix.anomaly` truthy or `o.price === 0` |
| `fakeDiscount` | `ix.fakeDiscount(o) !== null` (§6) |
| `linkFlag` | `o.ix.link` truthy |
| `complianceUnknown/Blocked` | `comp(p.id, state.country).status` (§3) |
| `riskLevel` | `ix.risk(m).level` (§11) |

`marketStats` (HTML 13102-13123), memoised per `productId:country` in `this._mk` (cleared on every
render, HTML 15479):

```js
os.forEach((o) => {
  const m = this.M(o.merchantId), z = m && m.zones[this.state.country];
  if (!z || !o.price) return;
  totals.push(o.price + (o.price >= m.freeOverEur ? 0 : z.cost));   // RAW price, no coupon
  ships.push(z.cost);
});
min: totals.length ? Math.min(...totals) : 0, median: ix.med(totals), shipMedian: ix ? ix.med(ships) : 4.9
```

Parity-relevant quirks (verified by execution):

* `marketMin` uses the **raw price without coupons** and the threshold on raw price, while the row
  total uses the coupon-reduced effective price. A couponed row can be below `marketMin`, so its
  price sub-score clamps to 1.
* `marketMin` **includes anomalous offers** (any `price > 0`). Example, DE / "Carb Loader
  Maltodextrin": the seeded anomaly (NordicGains, total 12.70) becomes `marketMin`, pushing every
  honest offer's price sub-score down (IronLab 16.79 → ratio 1.32 → price 0.08).

### 1.3 Formula (`intel.js:562-609`, verbatim core)

```js
const w = Object.assign({}, S.ix.rankWeights, ctx.weights || {});
const wsum = Object.keys(w).reduce((a, k) => a + w[k], 0) || 100;
const sub = {
  price: ctx.anomaly ? 0 : clamp(1 - ((ctx.total / (ctx.marketMin || ctx.total)) - 1) / 0.35, 0, 1),
  trust: clamp((ctx.trust || 60) / 100, 0, 1),
  delivery: clamp(1 - ((ctx.deliveryDays || 6) - 2) / 8, 0, 1),
  reviews: clamp(((ctx.rating || 4) - 3) / 2, 0, 1) * clamp(0.55 + Math.log10(1 + (ctx.reviewCount || 50)) / 6, 0, 1),
  freshness: clamp(1 - (ctx.freshnessHours || 12) / 72, 0, 1),
  availability: ctx.availability === 'in_stock' ? 1 : ctx.availability === 'low_stock' ? 0.7 : ctx.availability === 'preorder' ? 0.35 : 0,
  shipping: clamp(1 - (ctx.ship || 0) / ((ctx.shipMedian || 5) * 2 || 10), 0, 1),
};
Object.keys(w).forEach((k) => {
  const pts = (sub[k] || 0) * w[k] * (100 / wsum);
  score += pts;
  parts.push({ key: k, label: labelMap[k] || k, pts: Math.round(pts), max: Math.round(w[k] * (100 / wsum)) });
});
const quality = (ctx.completeness || 0.8) * 5 + (ctx.hasValidCoupon ? 2 : 0);   // max 7, NOT normalised
score += quality;
parts.push({ key: 'quality', label: 'Offer quality', pts: Math.round(quality), max: 7 });
```

Compact form: `score = Σ_k sub_k · w_k · 100/Σw + (completeness·5 + 2·hasValidCoupon) − penalties`,
then `clamp(Math.round(score), 0, 100)`. The unrounded sum is used; the per-part `pts` are rounded
independently, so **Σ parts.pts may differ from `score`** by rounding.

Penalties (`intel.js:589-597`), subtracted after the weighted sum:

| Condition | Label | Pts | Hidden |
|---|---|---|---|
| `(ctx.freshnessHours \|\| 0) > 48` | Stale data | 10 | no |
| `ctx.anomaly` | Price under review | 14 | no |
| `ctx.fakeDiscount` | Unverified reference price | 8 | no |
| `ctx.linkFlag` | Outbound link problem | 10 | **yes** |
| `ctx.complianceUnknown` | Market status not verified | 6 | no |
| `riskLevel === 'HIGH'` | Integrity signals | 6 | **yes** |
| `riskLevel === 'CRITICAL'` | Integrity signals | 14 | **yes** |

Bands (`intel.js:599-600`): `≥90 Exceptional`, `≥80 Excellent`, `≥70 Good`, `≥60 Fair`, else
`Low confidence`; colours `--ok / --acc-text / --text-2 / --warn / --danger`.

Best-buy eligibility: `!ctx.anomaly && !ctx.complianceBlocked && !ctx.complianceUnknown && score >= 60`.

Gotchas for PHP:

* `ctx.freshnessHours || 12`: an offer updated exactly at `S.NOW` (freshness 0) is scored as 12 h
  (sub 0.8333). 10 seeded offers hit this (verified).
* `ctx.trust || 60`, `ctx.rating || 4`, `ctx.reviewCount || 50`, `ctx.deliveryDays || 6`,
  `ctx.completeness || 0.8`, `ctx.shipMedian || 5` — all falsy fallbacks.
* Non-shipping rows still get a rank (`deliveryNum = 99`, `ship = 0`), but are filtered out by
  every listing (`.filter(r => r.ships)`).
* `Object.keys(w)` order drives the part order before sorting; custom weights that add a key not in
  `sub` score 0 for that key.

### 1.4 Output shape

```js
{ score, label, color,
  parts: parts.filter(p => p.pts !== 0).sort((a, b) => b.pts - a.pts),   // stable sort: ties keep key order price,trust,delivery,reviews,freshness,availability,shipping,quality
  penalties: penalties.filter(p => !p.hidden),                           // [{label, pts: -N, hidden:false}]
  hiddenPenalties: <count>,
  eligibleBestBuy: bool,
  weights: w }
```

Stored on the row (HTML 13143-13160): `rank, rankLabel, rankColor, rankWidth, rankEligible,
rankParts, rankPenalties` (plus `trustScore/…`, `priceConf/…`, `stockLabel/…`, `anomaly`,
`fakeDisc`, `showDiscount`, `couponState/…`).

"Why this rank?" modal (HTML 20446-20452):

```js
{ shop, score: row.rank, label, color, total,
  parts: rankParts.map(p => ({ label, pts: '+' + p.pts, max: 'of ' + p.max, width: round(p.pts / (p.max || 1) * 100) + '%' })),
  penalties: rankPenalties.map(p => ({ label, pts: p.pts + '' })),
  hasPenalties, sponsored: row.sponsored }
```

Divergence from `COMPARORANK.md`: the doc says the panel shows the count of withheld integrity
checks; `hiddenPenalties` is computed but **not copied to the row nor rendered** (the modal only
shows a generic sentence, HTML 11615).

### 1.5 Sorting and tie-breakers (HTML 13284-13301)

```js
const flaggedLast = (a, b) => (a.anomaly ? 1 : 0) - (b.anomaly ? 1 : 0);
rank: (a, b) => (b.rank || 0) - (a.rank || 0) || a.totalNum - b.totalNum,
c.sort((a, b) => flaggedLast(a, b) || cmp(a, b));
```

Order: non-anomalies first → rank desc → total asc → input order (Array.prototype.sort is stable;
input order is `allOffers()` = `S.offers` order + `state.newOffers`). Other modes: `total`, `price`
(effNum), `rating`, `delivery`, `discount`, `popular` (clicks30), `recommended` (legacy
`row.score = rating*8 - total*0.35 + (verified?6:0) + (in_stock?5:0) + (disc?3:0)`, HTML 12982).
`filterRows` (HTML 13302-13314) applies UI filters before sorting.

### 1.6 Commission / commercial isolation — confirmed

`rank()` reads only `ctx.*`, `S.ix.rankWeights` and `state.rankW`. Traced every ctx input:
`trust()` reads `m.ix.*`, `m.verified`, `m.rating`, `m.reviews`; `risk()` reads `m.ix.*` and
`S.ix.riskEvents`; `completion()` reads product fields; `couponMeta()` reads `c.ix`, `c.ends`.
**None reads** `m.affiliate` (commission, network, cookie), `m.tier`, `m.partner`, `o.sponsored`,
`o.clicks30`, `S.affiliate`, `S.cx` (plans, campaigns, invoices), `S.vs` bookings or `S.gp`
enrolments. The row carries `sponsored`, `partner`, `tier` for display only. `visibility.assemble`
(promoted-slot insertion) is only used in the rate-card preview (HTML 19491-19492), not on offer
tables.

Indirect commercial influence (seed-time, not rank-time) worth recording for the port:

* Comparo-exclusive coupons exist only for merchants 1, 2, 3, 5 (`seed.js:185-193`) and FREE-tier
  shops get 1 coupon vs 2–3 (`seed.js:171`). Coupons lower `total` and give +2 quality, so
  commercial relationships can move rank *through the price*. This is by design (a real coupon is a
  real price) but should be stated.
* Delivery reach in `seed-geo.js:98-104` depends on `m.partner` / `m.tier` (which markets a shop
  ships to), and offer prices get `× 0.97` for FREE tier at seed time (`seed.js:201`).

### 1.7 Determinism

Deterministic given `(SEED, state.country, state.rankW, state.ovMerch/ovOffers/ovComp/newOffers/
extraCoupons/merges/proofs/ovRev/addedReviews)`. No `Math.random`, no `Date.now()` on this path.

---

## 2. Total landed price

### 2.1 Location

`offerRow(o)` HTML **12928-12985** (memo `this._rc[o.id:country:currency]`, cleared each render).
Helpers: `allCoupons()` HTML 12867 (`S.coupons.concat(state.extraCoupons)`), `M()` HTML 12835
(merchant merged with `state.ovMerch[id]`), `ix.couponMeta` `intel.js:468-483`,
`shipFor(mid, sub)` HTML 13192-13196 (basket), `publicRows` HTML 13317.

### 2.2 Algorithm (verbatim)

```js
const m = this.M(o.merchantId), iso = this.state.country, zone = m.zones[iso], p = this.P(o.productId);
const ships = !!zone;
const flagged = !!(o.ix && o.ix.anomaly) || !(o.price > 0);
const cps = flagged ? [] : this.allCoupons().filter((c) => c.merchantId === m.id && c.countries.indexOf(iso) >= 0 && c.ends > this.S.NOW && o.price >= c.minOrder
  && !(ixe && ixe.couponMeta(c) && ixe.couponMeta(c).usable === false));
let best = null, bestSave = 0;
cps.forEach((c) => {
  let save = 0;
  if (c.type === 'percent') save = o.price * c.value / 100;
  else if (c.type === 'fixed') save = c.value;
  else save = ships && o.price < m.freeOverEur ? zone.cost : 0;       // 'freeship'
  if (save > bestSave) { bestSave = save; best = c; }
});
const eff = (!flagged && best && best.type !== 'freeship') ? Math.round((o.price - bestSave) * 100) / 100 : o.price;
let ship = ships ? (eff >= m.freeOverEur ? 0 : zone.cost) : 0;
if (best && best.type === 'freeship') ship = 0;
const total = Math.max(0, Math.round((eff + ship) * 100) / 100);
const disc = o.oldPrice ? Math.round((1 - o.price / o.oldPrice) * 100) : 0;
```

Coupon selection rules:

* Types: `percent` (value %), `fixed` (value €), `freeship` (value 0; worth `zone.cost` only when
  the raw price is under the threshold).
* Scoping: same `merchantId`; `c.countries` contains the selected market; **no product scoping**
  (coupon records have no product field); `o.couponId` on the offer is **ignored** by the price
  pipeline.
* Validity: `c.ends > S.NOW` and `couponMeta(c).usable !== false` (excludes states `expired` and
  `invalid`; `couponMeta` also forces `expired` when `c.ends < NOW`). `c.starts` is **not checked**.
* Minimum basket: compared with the **single-unit raw price** `o.price >= c.minOrder`.
* Winner: largest saving, strict `>`; ties → first in `allCoupons()` order (seed order, then user
  coupons). A coupon worth 0 never wins. Savings are compared unrounded.
* Flagged offers (anomaly or price ≤ 0) get no coupon and `eff = o.price`.
* No floor on `eff`: a fixed coupon larger than the price gives `eff ≤ 0`; such rows are removed by
  `publicRows` (`!r.anomaly && r.effNum > 0 && r.totalNum > 0`). None in current seed (verified).

Shipping rules:

* `zone = m.zones[country]` — `{ cost, days: [min, max], carrier, duty }` (`seed.js:135-138`,
  `seed-community.js:77-79`, extended in `seed-geo.js:98-128` with `bandMult {1:1.25, 2:1.7, 3:2.3}`).
  No zone → `ships = false`, `ship = 0`, delivery "—", `deliveryNum = 99`.
* Free-shipping threshold `m.freeOverEur` is tested against the **coupon-reduced unit price**
  (`eff >= freeOverEur`), while the freeship coupon's value and `marketStats` test the **raw
  price**. Threshold is per single unit (no quantity) in `offerRow`; per basket subtotal in
  `shipFor` (`sub >= m.freeOverEur ? 0 : z.cost`).
* Customs (`country.customs`, `seed-geo.js:70-83`) is **display-only**; never added to the total.

Currency: every price, cost and threshold is stored and compared in **EUR**. Conversion happens
only in formatting: `S.helpers.fmt(eur, code)` (`seed.js:477-484`) → `v = eur * currency.rate`,
0 decimals for `CZK|SEK|PLN`, else 2, via `Intl.NumberFormat(locale, {style:'currency'})`.
Rates: `seed.js:17-24` (USD 1.08, GBP 0.85, CZK 25.2, PLN 4.31, SEK 11.2) + `seed-geo.js:30-32`
(DKK 7.46, NOK 11.62, CHF 0.94, HUF 395, RON 4.97, BGN 1.96). `fmtUnit` (HTML 16758) picks 2/3/4
decimals by magnitude. Fixtures should compare the numeric fields (`effNum`, `shipNum`,
`totalNum`), never formatted strings (ICU-dependent).

### 2.3 Output (row fields relevant to price)

`price, eff, effNum, hasOld, old, discount, discNum, shipNum, ship ('n/a'|'Free'|fmt),
total, totalNum, unit (eff / servings), deliveryDays, deliveryNum (zone.days[1]), freeOver,
hasCoupon, couponCode, couponTitle, couponExclusive, ships, availNum (3/2/1/0)`.

Deterministic: yes (depends on state country/currency/overrides).

### 2.4 Basket (`ix.basket`, `intel.js:879-933`)

Pure; host injects `rowsFor(pid)` = `basketRows` (HTML 13191, `publicRows` of `offerRow`s) and
`shipFor(mid, sub)`. Outputs `single` (shops carrying every line, sorted by total), `split`
(cheapest `effNum` per line, shipping once per shop), `lowShip`, `trustFirst`, `nudges`
(`need = freeOverEur - sub`), `best = split.total < single[0].total - 0.5 ? 'split' : 'single'`.

---

## 3. Compliance / market filtering

Data: `S.complianceRules` `{ id, productId, country, status, reason, source, reviewedBy,
reviewedAt }` — `seed.js:252-270`, `seed-community.js:311`, `seed-geo.js:137-150`.

`comp(pid, iso)` HTML 12877-12882: `state.ovComp[pid-iso]` override → matching rule → default
`{ status: 'allowed', reason: 'No country-specific restriction on record.', source: 'Default policy' }`.

What the prototype actually does per status (verified in code):

| Status | Product page offers (HTML 15858-15861) | Rank | Best value | Deal hub (16209) | Ask Comparo (12045-12051) |
|---|---|---|---|---|---|
| `allowed` | shown | normal | yes | yes | answers |
| `restricted` | **shown** + warning banner | normal | yes | **yes** | answers |
| `unknown` | **shown** + "review pending" banner | −6 penalty | no (`complianceUnknown`) | **yes** | "not verified" |
| `prescription_only` | **hidden** (`rows = []`) | computed, not eligible | no | excluded | "not purchasable" |
| `not_allowed` | **hidden** | computed, not eligible | no | excluded | "not purchasable" |

Other gates: `buildProduct` still computes `pubRows`/`priceStats` from all shipping rows even when
blocked (HTML 15862, 16036); `buildCompare` (HTML 16141-16160) and `productCard` (13334-13351)
apply **no compliance filter** (unverified whether other card lists do). Shipping filter:
`r.ships` (merchant has a zone for the country).

Divergence from `COMPLIANCE.md`: the doc hides offers for `unknown` and excludes `restricted` from
the deal hub/recommendations; the prototype does neither on those surfaces. The Laravel port must
decide which is canonical; fixtures from the prototype will encode the prototype behaviour.

---

## 4. Merchant Trust Score 2.0

`trust(m)` `intel.js:40-94`, memo key `'trust' + m.id + m.rating`.

Inputs: `m.ix` (from `seed-intel.js:47-67`: `bizVerified, accountAgeDays, verifiedReviewRatio,
complaintRate, resolution, responseRate, verifiedOrderRate, priceAccuracy, feedUptime,
shipAccuracy, brokenLinkRate, communityReports, deliveryOnTime`), `m.verified`, `m.rating`,
`m.reviews`.

```js
biz: a.bizVerified ? 1 : m.verified ? 0.55 : 0.1,                        // 14
age: clamp((a.accountAgeDays || 200) / 900, 0, 1),                       // 6
rating: clamp((m.rating - 3) / 2, 0, 1),                                 // 14
verifiedRatio: clamp((a.verifiedReviewRatio || 50) / 100, 0, 1),         // 8
complaint: clamp(1 - (a.complaintRate || 2) / 5, 0, 1),                  // 10
resolution: clamp((a.resolution || 70) / 100, 0, 1),                     // 10
response: clamp((a.responseRate || 70) / 100, 0, 1),                     // 6
orders: clamp((a.verifiedOrderRate || 60) / 100, 0, 1),                  // 6
priceAcc: clamp(((a.priceAccuracy || 92) - 70) / 30, 0, 1),              // 8
feed: clamp(((a.feedUptime || 90) - 55) / 45, 0, 1),                     // 8
ship: clamp(((a.shipAccuracy || 88) - 55) / 45, 0, 1),                   // 6
links: clamp(1 - (a.brokenLinkRate || 1) / 8, 0, 1),                     // 4   (weights sum 100)
penalty = clamp(communityReports * 0.22, 0, 8);
score = clamp(Math.round(Σ sub·weight - penalty), 0, 100);
```

Note `complaintRate || 2`: a merchant with 0 % complaints scores as 2 %. Labels: `≥90 Highly
trusted, ≥78 Trusted, ≥65 Generally reliable, ≥50 Mixed signals, else Low trust`.

Output: `{ score, label, color, signals: [{key,label,weight,pts:r1,pct:round(sub*100)}],
public: [6 × {label, value, pct, good}], penalty: r1, reports }`. The public list includes
"Shipping reliability" from `deliveryOnTime` which is **not** one of the 12 weighted inputs.

`trustHistory(m, days)` `intel.js:95-103`: re-anchors `m.ix.trustSeries` on the current score.

Legacy duplicate: `trustScore(m)` HTML 11978-11997 is a **different formula** (7 parts, weights
25/15/15/15/10/10/10, feed freshness from `S.feeds.lastRun`, labels Excellent/Good/Mixed/Weak).
It is used by Ask Comparo shop sorting (12069-12078), the visibility preview (19489), market pages
(19949) and a report table (20015). ComparoRank uses `ix.trust`, not this one.

Deterministic: yes.

---

## 5. Price confidence and price anomaly detection

`priceConfidence(o)` `intel.js:381-403` (cache key `'pc'+id+price+updated`):

```js
hit('Fresh feed (< 24 h)', 24, ageH <= 24);
hit('Merchant verified', 12, !!(m && m.verified));
hit('Historically consistent', 22, !(o.ix && o.ix.anomaly));
hit('Valid currency & price', 18, o.price > 0);
hit('Stock state present', 10, !!o.availability);
hit('Shipping known', 8, !!(m && m.zones));            // any zones object, not market-specific
hit('Link healthy', 6, !(o.ix && o.ix.link));
score = clamp(Math.round(100 - Σmissed), 0, 100);  levels ≥88 High, ≥70 Moderate, ≥50 Low, else Unreliable
```

Output `{ score, signals: [{label, ok, pts: 0|-N}], ageHours: r1, level, color }`. `M()` here is
the raw seed merchant (no `ovMerch`).

Host adjustment `confidenceOf(o, extra)` HTML 13228-13255: open shopper reports on the offer
(`state.reports`, `state !== 'Resolved'`) weighted `{ price_correct: 7, price_changed: -15,
out_of_stock: -11, coupon_invalid: -5 }`, halved if older than 14 days (`this.now()` — live clock),
`delta = clamp(round(Σ), -34, +12)`, adds `reportDelta`, `reportCount` and extra signals.

`anomalies()` `intel.js:404-432`: per product, `m = med(prices > 0)`;
`price === 0 → 'Zero price'`, `price < m*0.45 → 'Far below market'`, `price > m*2.2 → 'Far above
market'`; plus `S.ix.feedDiff` rejected rows; sorted by `|diff|` desc. Runtime flagging in the offer
pipeline, however, uses the **seeded flag `o.ix.anomaly`** (`seed-intel.js:190`) or `price === 0`,
not `anomalies()`.

Deterministic: yes (`priceConfidence`), `confidenceOf` depends on live clock only when reports exist.

---

## 6. Deal Score, real discount, price stats, trend/timing

`histStats(p)` `intel.js:339-351` over `p.hist.min` (365 daily minima, `seed.js:225-250`, extended
in `seed-community.js`): `avg7/30/90/365 (r2)`, `low, high, median (upper middle), low30, low90,
volatility = r1((max90 - min90) / (avg90 || 1) * 100), cur = h[n-1]`.

`priceBadge(cur, st)` `intel.js:352-358`: `cur <= low90*1.02 → Exceptional`; `ratio = cur/avg90`;
`≤0.94 Good`, `≤1.06 Typical`, else `Above average`.

`timing(cur, st)` `intel.js:359-365`: `gap = (cur/low90 - 1)*100`; `≤3 Strong`, `≤10 Reasonable`,
`≤22 Worth waiting`, else `Poor timing`.

`forecast(p)` `intel.js:366-380`: `volatility > 26 → Highly volatile`; `ma7 < ma30*0.975 && ma30
<= ma90 → Downward`; `ma7 > ma30*1.025 && ma30 >= ma90 → Upward`; else `Likely stable`.

Host usage (HTML 16051-16070, `pdIntel`): `cur = top ? top.totalNum : st.cur` where `top` is the
best-ranked eligible row — i.e. the badge compares a **landed total** with a history of **raw daily
minimum prices**.

Second, separate implementation `priceStats(p, rows)` HTML 11954-11977 (product page `pdPrice`,
called with `shipsHere`, which **includes anomalous rows**): `cur = min(effNum)`, `vs90 =
round((cur/avg90 - 1)*1000)/10`, volatility = **population std-dev / avg90** (not range/mean),
badges `All-time low | Near historical low (≤ lowestEver*1.03) | Good price (vs90 ≤ -5) | Above
average (vs90 ≥ 6) | Average price`.

Real discount: `disc = o.oldPrice ? Math.round((1 - o.price / o.oldPrice) * 100) : 0` (HTML 12952).
Shown only when `!fake && !anomaly` (`showDiscount`, HTML 13151-13153).
`fakeDiscount(o)` `intel.js:433-446`: `refVsMedian = oldPrice / (histStats.median || oldPrice)`;
fake when `≥ 1.25` → returns `{ claimed, now, disc, median90: st.avg90, note, raisedAt }` else null.

`dealScore(d, ctx)` `intel.js:486-514` — **exported but has no caller in the HTML** (grep):

```js
vs = 1 - price / (st.avg90 || price);          add('Discount vs 90-day average', clamp(vs * 120, -10, 34));
add('Distance to historical low', clamp((1 - (price / (st.low || price) - 1) * 4) * 18, 0, 18));
add('Shop trust', clamp((trust - 50) / 50 * 18, -6, 18));
add('Coupon validity', invalid ? -10 : usable ? 10 : 0);
add('Stock', in_stock 8 | low_stock 4 | else 0);
add('Deal freshness', clamp(10 - ageH / 12, -4, 10));   // ageH = d.ts ? (NOW - d.ts)/HOUR : 12
score = clamp(Math.round(50 + Σ), 0, 100);  ≥85 Outstanding, ≥72 Strong, ≥58 Decent, ≥44 Weak, else Not a deal
```

The Deal Hub (`dealItems`, HTML 16205-16230) lists price drops with `discNum >= 5 && showDiscount`
and active coupons for the market; it does not compute a deal score. "Deal score" in methodology
copy (HTML 20070) refers to community vote confidence (unverified where computed).

`bestValue(rows)` HTML 17820-17844 (`pdBest`): pool = eligible rows → else non-anomalous → else all;
`valueScore = r.rank` whenever a rank exists (legacy 50/25/15/10 formula is a fallback only);
winner = highest `valueScore`, ties → first in the (already sorted/filtered) rows.

Deterministic: yes.

---

## 7. Matching engine

`similarity(a, b) = r2(0.5 * jaccard + 0.5 * trigram)` `intel.js:19-37`:
`norm(s) = H.norm(s).replace(/[^a-z0-9 ]+/g, ' ').replace(/\s+/g, ' ').trim()` where
`H.norm = toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')` (`seed.js:440`);
`tokens` = words with length > 2; Jaccard over token **sets**; trigram = Dice
`2·|A∩B| / (|A|+|B|)` over 3-char substrings of the normalised string (spaces included).

`match(item)` `intel.js:249-284` scores every product, keeps the max (`score > best.score`, ties →
first product):

| Signal | Pts |
|---|---|
| `item.ean === p.ean` | +50 |
| brand exact (`H.norm`) or alias from `S.ix.brandAliases` | +15 |
| else brand name found in raw title | +9 |
| `Math.round(similarity(raw, p.name+' '+p.pack+' '+brand) * 22)` | 0–22 |
| `packRaw` equals `p.pack` | +10 |
| else in `p.packs` | +6 |
| else (`packRaw` present) | −12 (applied once, `:274`; the part at `:269` is display-only) |
| variant in `p.variants` | +7 |
| first word of any ingredient in raw | +5 |

`score = clamp(round(sc), 0, 100)`; levels `100 Exact, ≥90 Very high, ≥80 High, ≥65 Possible,
else Manual review`; **bucket `≥90 auto`, `≥65 confirm`, else `unmatched`**; feed rows with
`status === 'compliance_hold'` go to `conflicts` (`matchBuckets`, `:315-326`). Output
`{ score, level, color, bucket, product, parts: [{label, pts}] (pts ≠ 0) }`. Inputs: `S.feedItems`
(`seed.js:368-379`, `seed-intel.js:289-310` adds `brandRaw/packRaw/variantRaw`), `S.products`,
`S.brands`. `variantGuard(a, b)` `:285-294`; `clusters()` `:295-314` (score ≥ 65).

Deterministic: yes.

---

## 8. Review credibility weighting and aggregate rating

`reviewWeight(r)` HTML 12886-12896:

```js
if (mine && proof && proof.status === 'verified') return 1;    // own review with click proof
if (r.verifiedPurchase) return 1;
const conf = ixe.reviewTrust(r).level;
'Suspicious' → 0.25; 'Needs review' → 0.6; otherwise → 0.75
```

`reviewTrust(r)` `intel.js:131-159`: `100 −` penalties (duplicate 34, burst 24, account < 14 d 14,
no verified purchase 10, shared device (>2 reviews) 16, repeated target 8, body < 60 chars 6);
levels `≥85 High confidence, ≥65 Normal, ≥45 Needs review, else Suspicious`. Inputs
`S.ix.reviewFlags` (`seed-intel.js:95-165`), `S.users.joined`, `S.reviews`.

`ratingOf(type, id)` HTML 12897-12925 over `approved(allReviews())` (status `approved`, with
`state.ovRev` overrides, merges and `state.addedReviews`):

* merchant: `heldAvg = Σ(rating·w)/Σw`; `share = min(1, n / max(n, m.reviews))`;
  `avg = round1(m.rating·(1-share) + heldAvg·share)`; returns `{avg, count: m.reviews, weighted,
  heldCount, heldAvg, verifiedCount}`; no held reviews → `{avg: m.rating, count: m.reviews}`.
* product: `avg = round1(Σ(rating·w)/Σw)`, `count = n`; none → `{avg: 0, count: 0}`.

The merchant average feeds ComparoRank `ctx.rating`. The rating distribution, "% would recommend"
and `reviewSummary` average (HTML 17807-17819) are **unweighted**.

Deterministic: yes.

---

## 9. Dosing (cost per active gram / per serving)

Data `seed-dose.js`: `D` map slug → mg per serving (`:15`), `CARRIER = ['Maltodextrin', 'Cyclic
dextrin']` (`:68`), `NRV` (`:72`), `LIMITS` (`:76`); sets `p.doses = [{ingredient, mg, carrier,
nrv}]`, `p.doseSource` (`id%7===0 brand_spec`, `id%3===0 merchant_feed`, else `label_photo`),
`p.doseUpdated = NOW - ((id*13) % 210) * DAY`, `S.doseMeta`.

HTML helpers (16763-16867):

```js
servingMg(p) = p.unit === 'g' ? parseFloat(p.pack) * 1000 / p.servings : null
activeMg(p)  = Σ d.mg where !d.carrier
packActiveG(p) = activeMg(p) * p.servings / 1000
costPerActiveG(p) = bestFor(p.id).totalNum / packActiveG(p)        // null if no dose/offer
costPerIngredientG(p, name) = bestFor(p.id).totalNum / (d.mg * servings / 1000)
bestFor(pid) = sortRows(publicRows(shipping offerRows), 'total')[0]   // HTML 16932-16935
cheapestSourceOf(name) → dosed canonical products sorted by costPerIngredientG asc
```

Note: `parseFloat('4 kg')` would be 4 (no kg handling) — current packs are in g (unverified for all
46 products). `doseLimits(p, iso)` HTML 16818-16841 compares the **per-serving** mg with `l.max`
for both scopes (`const daily = l.scope === 'daily' ? d.mg : d.mg;` — no servings/day factor).
Per-serving price: `row.unit = eff / p.servings` (HTML 12970); `productCard` uses min `effNum /
servings`; `ix.unitEconomics` (`intel.js:447-457`) exists but has no HTML caller.

Deterministic: yes.

---

## 10. Delivery reliability / order metrics

Three independent implementations:

1. `ix.deliveryReliability(m)` `intel.js:534-545` — **seeded percentage** `m.ix.deliveryOnTime`
   (default 85), `sample = max(shipMentions in reviews, 12)`, colour ≥92 ok / ≥80 acc / warn.
2. `deliveryStats(mid, iso)` HTML 16873-16894 — measured from `S.orders`: requires
   `done.length >= S.orderMeta.minSample (8)`; `medianDays` = averaged-middle median (r1);
   `p90 = sorted[min(n-1, floor(n*0.9))]`; `promised = mean(promisedDays)`; `onTimePct =
   round(actualDays <= promisedDays share *100)`; `returnPct`, `disputePct` over all orders (r1).
3. `seed-addons.js:232-249` `gp.measured` (used by `addons.eligibility`, `addons.js:151-166`):
   averaged median + `r2`, `p90 = sorted[ceil(n*0.9) - 1]`, `onTime = pct()` to 1 decimal.
   **p90 and rounding differ from (2).**

Orders (`seed-orders.js:44-116`): market = buyer country if shipped there else home; `unit =
offer.price` (captured **before** `seed-intel` rewrites anomaly prices); `shipping = itemTotal >=
freeOverEur ? 0 : zone.cost` (no coupons); `promisedDays = zone.days[1] + 1`; dispatch `2–18 h`
verified / `8–58 h` otherwise; transit `int(days[0], days[1] + (verified?0:2)) + disruption
(3.4 %: 2–6 d)`; status by `NOW` (placed / in_transit / delivered, then 5.5 % returned, 2 %
disputed); `actualDays = r2((deliveredAt - placedAt)/DAY)`.

Delivery promise eligibility (`addons.js:151-166`): n ≥ 8, onTime ≥ 92, p90 ≤ window, dispute rate
< 2 %, deposit held ≥ required.

Deterministic: yes.

---

## 11. Fraud / risk (brief)

* `risk(m)` `intel.js:106-128`: unverified ownership 16; complaint `(rate-1.2)*6` ≤ 18; feed
  `(97-uptime)*0.55` ≤ 18; broken links `rate*1.7` ≤ 16; price accuracy `(97-acc)*0.9` ≤ 14;
  reports `*0.5` ≤ 12; response `(80-rate)*0.22` ≤ 10; each `S.ix.riskEvents` of the merchant
  `sev{LOW 4, MEDIUM 10, HIGH 18, CRITICAL 30} * 0.55` (no time decay, contrary to
  `TRUST-SCORING.md`); levels `≥62 CRITICAL, ≥42 HIGH, ≥22 MEDIUM`. Uses raw `a.*` without
  defaults (NaN-prone for merchants without `m.ix`; all seeded merchants have it).
* `reviewTrust` (§8), `dupClusters` (`:160-188`, similarity ≥ 55), `burst` (`:189-201`, 72 h
  histogram, flagged `peak ≥ 6 && ratio ≥ 8`), `manipulation` (`:202-219`, 7-day delta ≥ 0.45),
  `userTrust` (`:220-237`, no caller found). All pure, `S.NOW`-based.

---

## 12. Recommendations, personalisation, growth, commercial (brief)

| Function | Location | Signature / inputs | Purity |
|---|---|---|---|
| `related(p)` | `intel.js:612-631` | category +30, brand +22, ingredient ×14, price band ±20 % +14, `S.ix.demand.compares30 > 40` +8, unit +4, threshold 30 | pure |
| `similarShops(m)` | `intel.js:632-653` | markets ×4, categories ×7, trust ±10 +8, rating ±0.3 +6, top 6 | pure |
| `trends()` | `intel.js:656-684` | `S.ix.demand`, offers `clicks30`, reviews, trust; `decay(v, 0)` is a no-op | pure |
| `personal(sig)` | `intel.js:936-988` | `sig = {country, saved, follows, searches, compares, dealClicks}` from `personalSignals()` HTML 13184-13190 (user state) | pure given sig |
| `commercial(m)`, `performance(m)`, `affiliateMetrics(m)` | `intel.js:738-794` | **read `S.affiliate`, `m.affiliate`, `m.partner`, `m.tier`** — merchant/admin analytics only, never ranking | pure |
| `ComparoGrowth(S, ix)` → `opportunityScore(o)` | `growth.js:4`, `:256-269` | caps: demand 20, commercial 18, data 12, gap 14, user 12, seo 12, revenue 14, effort Low 8 / Medium 4 | pure, `S.NOW`, memo |
| `ComparoCommercial(S, ix, gr, ctx)` | `commercial.js:4` | `S.cx`; `ctx.invoices()` host seam (state-merged ledger); `fxTo` via `cx.settings.fx` | pure given ctx |
| `ComparoAddons(S)` | `addons.js:10` | `S.cx`, `S.gp`, `S.orders` | pure |
| `ComparoGovernance(S, GM)` | `governance.js:10` | jury draw LCG seeded by case id; `now()` = `helpers.now` | pure except live clock |
| `ComparoLive(S)` | `live.js:22` | `Math.random`, `Date.now`, `localStorage`, `BroadcastChannel`, timers | **not deterministic; exclude** |

---

## 13. Parity fixture export plan

### 13.1 How the files load (verified)

| File | Shape | Global written | Load-time side effects |
|---|---|---|---|
| `support.js` | `"use strict"` IIFE (dc-runtime, React bootstrap) | many (`Object.assign(window, api)`) | touches `document.head/body`, `customElements`, loads React UMD — **skip in headless** |
| `seed.js` | IIFE | `window.SEED = {...}` | `Intl.*` in helpers (call time only) |
| other `seed-*.js` | IIFE, `const S = window.SEED; if (!S) return;` | mutate `S` | none, **except `seed-visibility.js`** |
| `seed-visibility.js` | IIFE | `S.vs`, `S.helpers.now/ago/sanitizeStamps` | reads/writes `localStorage` (`comparo.clock.v2`, `comparo.proto.v2`), `Date.now()`, `setInterval(save, 5000)`, `window.addEventListener('pagehide'|'beforeunload')` (all in try/catch) |
| `intel.js`, `growth.js`, `commercial.js` | top-level `window.X = function (S, …)` assignment | `ComparoIntel`, `ComparoGrowth`, `ComparoCommercial` | none |
| `labels.js`, `visibility.js`, `governance.js`, `gamify.js`, `addons.js` | IIFE assigning `window.X` | `ComparoLabels`, `ComparoVisibility`, `ComparoGovernance`, `ComparoGamify`, `ComparoAddons` | none |
| `live.js` | IIFE | `ComparoLive` | factory only; `Math.random`/`Date.now`/storage at call time — skip |

No file uses top-level `const`/`let` globals shared across scripts; everything communicates through
`window.SEED` and `window.Comparo*`. None touches `document` at load time except `support.js`.

### 13.2 Minimal sandbox (`node:vm`, one shared context, HTML load order minus `support.js` and `live.js`)

```js
// tools/prototype-parity/export-fixtures.mjs (sketch — recipe verified with Node 24.16)
import vm from 'node:vm'; import fs from 'node:fs'; import path from 'node:path';
const ROOT = process.argv[2] ?? '.';
const ORDER = ['seed.js','seed-community.js','seed-geo.js','seed-live.js','seed-gamify.js','seed-dose.js',
  'seed-orders.js','seed-labels.js','seed-network.js','labels.js','seed-visibility.js','seed-governance.js',
  'visibility.js','governance.js','seed-seo.js','seed-intel.js','intel.js','seed-growth.js','growth.js',
  'seed-commercial.js','commercial.js','seed-addons.js','gamify.js','addons.js'];
const FROZEN = Date.parse('2026-09-06T09:00:00Z');                   // === S.NOW
const mem = new Map();
const localStorage = { getItem: k => mem.has(k) ? mem.get(k) : null, setItem: (k, v) => mem.set(k, String(v)),
  removeItem: k => mem.delete(k), clear: () => mem.clear() };
class FixedDate extends Date { constructor(...a) { super(...(a.length ? a : [FROZEN])); } static now() { return FROZEN; } }
const sb = { console, Intl, localStorage, sessionStorage: localStorage, Date: FixedDate,
  setInterval: () => 0, clearInterval() {}, setTimeout: () => 0, clearTimeout() {},
  addEventListener() {}, removeEventListener() {}, location: { hash: '', href: 'http://localhost/' },
  document: { readyState: 'complete', addEventListener() {}, body: null, head: { appendChild() {} } } };
sb.window = sb;
sb.Math = Object.create(Math); sb.Math.random = () => { throw new Error('Math.random in scoring path'); };
const ctx = vm.createContext(sb);
for (const f of ORDER) vm.runInContext(fs.readFileSync(path.join(ROOT, f), 'utf8'), ctx, { filename: f });
```

Shims actually required: `window` (= global), `localStorage` (Map-backed, empty), fixed `Date`
(constructor with no args and `Date.now()` → `S.NOW`; `new Date(iso)` must still parse),
no-op `setInterval`/`addEventListener`. `document`, `location`, `sessionStorage`, `setTimeout`
are only needed for the HTML component (13.3). Overriding `Math.random` to throw is a cheap guard
that the scoring path stays deterministic (it did not fire in the verified run).

### 13.3 HTML-embedded logic: evaluate the Component class, do not hand-copy

The offer pipeline (§1.2, §2, §3, §8, §9, §10(2)) exists **only** in the HTML. The cleanest
extraction is to evaluate the whole class (lines 11679–20491, one `<script type="text/x-dc">`
block closing at 20492) in the same context with a stub base class — verified working:

```js
const lines = fs.readFileSync(path.join(ROOT, 'Comparo Performance.dc.html'), 'utf8').split(/\r?\n/);
const start = lines.findIndex(l => l.startsWith('class Component extends DCLogic'));   // 11678 (0-based)
const end = lines.indexOf('</script>', start);                                          // 20491 (0-based)
vm.runInContext(
  'class DCLogic { constructor(p) { this.props = p || {}; } setState(u) { const x = typeof u === "function" ? u(this.state) : u; this.state = Object.assign({}, this.state, x); } }\n'
  + lines.slice(start, end).join('\n') + '\nglobalThis.Component = Component;', ctx, { filename: 'component.js' });
const app = new ctx.Component({});          // constructor reads location.hash + localStorage (empty → defaults)
function forMarket(iso, currency = 'EUR') { app.state.country = iso; app.state.currency = currency; app._mk = {}; app._rc = {}; }
```

Locate the class by marker text, not by fixed line number, and fail loudly if the marker or the
`</script>` is missing. Always reset `app._mk` and `app._rc` when switching market/currency/state
(the app does this at the top of `renderVals`, HTML 15479). `ix` is created lazily and memoised in
`app._ix` (HTML 12988-12991); its internal `cache` is not market-dependent.

Fallback if the class ever stops evaluating: extract these method ranges and mount them on a plain
object whose `state`/`S` you supply:

| Method(s) | HTML lines |
|---|---|
| `priceStats`, `trustScore`, `pricePosition` | 11954-12011 |
| `P`, `M`, `CO`, `fmt`, `stars`, `mergedInto`, `canonicalProduct`, `allOffers`, `allOffersRaw`, `allCoupons`, `allReviews`, `approved`, `comp`, `reviewWeight`, `ratingOf` | 12833-12925 |
| `offerRow` | 12928-12985 |
| `ix` | 12988-12991 |
| `weights`, `marketStats`, `enrichRow` | 13101-13169 |
| `personalSignals`, `basketRows`, `shipFor`, `basketResult` | 13184-13204 |
| `reportWeights`, `confidenceOf` | 13228-13255 |
| `sortRows`, `filterRows`, `publicRows`, `canonicalProducts` | 13284-13333 |
| `productCard` | 13334-13351 |
| `proofFor` | 12434-12437 |
| `buildProduct` (needs many more helpers — prefer class eval) | 15843-16130 |
| `fmtPrec`, `fmtUnit`, dose helpers, `cheapestSourceOf`, `allOrders`, `deliveryStats`, `orderRow`, `bestFor` | 16750-16935 |
| `insights`, `reviewSummary`, `bestValue` | 17790-17844 |
| `now` | 17947 |
| Why-rank payload (`wrData`) | 20441-20454 |
| Ask Comparo compliance branch | 12036-12065 |
| Deal hub compliance gate | 16205-16230 |

Required `state` keys for those methods: `country, currency, ovOffers, ovMerch, ovComp, ovRev,
newOffers, extraCoupons, addedReviews, merges, reports, proofs, rankW, sort, fAvail, fFree,
fVerified, fDeal, fCoupon, fRating, fWarehouse` (defaults in HTML 11683-11729).

### 13.4 What to export per algorithm

Always export first: `seed.snapshot.json` = `JSON.stringify(ctx.SEED)` after the full load order
(input for the PHP side; replaces re-implementing generators; includes the in-place mutations and
coupon/zone aliasing of §0.2). Drop `helpers` (functions) and record `S.NOW`.

| # | Algorithm | Call(s) | Fixture record |
|---|---|---|---|
| 1 | ComparoRank | `app.offerRow(o)` for every offer × market (27) [+ Ranking Lab weight variants via `app.state.rankW`]; also raw `ix.rank(ctx)` with synthetic ctx edge cases (falsy fallbacks, clamps, each penalty). Capture the exact ctx by wrapping before the sweep (verified): `const ix = app.ix(), orig = ix.rank; ix.rank = (c) => { captured.push(c); return orig(c); };` | captured ctx (input fixture for the PHP scorer) + full `ix.rank` result incl. `hiddenPenalties`, and the row fields `rank, rankLabel, rankEligible, rankParts, rankPenalties` |
| 1b | Sort order | `app.sortRows(rows, mode)` for all 8 modes | ordered offer ids |
| 2 | Landed price | same `offerRow` sweep; `app.marketStats(pid)`; `ix.basket` via `app.basketResult()` with fixed baskets | `ships, effNum, shipNum, totalNum, couponCode, discNum, deliveryNum`; market stats |
| 3 | Compliance | `app.comp(pid, iso)` for 46 × 27; `app.buildProduct(slug).pdRows.length` + `pdCompliance` | status, blocked, visible row count |
| 4 | Trust | `ix.trust(m)`, `ix.trustHistory(m, 30)`, legacy `app.trustScore(m)` | full objects |
| 5 | Price confidence / anomalies | `ix.priceConfidence(o)`, `app.confidenceOf(o, syntheticReports)`, `ix.anomalies()` | full objects |
| 6 | Price stats / deals | `ix.histStats`, `priceBadge`, `timing`, `forecast`, `fakeDiscount`, `dealScore` (synthetic `d`), `app.priceStats(p, rows)`, `app.bestValue(rows)` | full objects |
| 7 | Matching | `ix.similarity` (string pairs), `ix.match(item)` for `S.feedItems`, `ix.matchBuckets()`, `ix.variantGuard` | score, bucket, product id, parts |
| 8 | Reviews | `ix.reviewTrust(r)`, `app.reviewWeight(r)`, `app.ratingOf('merchant'|'product', id)` | per review / per target |
| 9 | Dosing | `app.servingMg/activeMg/packActiveG/costPerActiveG(p)` per market, `app.cheapestSourceOf(name)`, `app.doseLimits(p, iso)` | numbers |
| 10 | Delivery | `ix.deliveryReliability(m)`, `app.deliveryStats(mid, iso)`, `ComparoAddons(S).eligibility(mid, iso)`; `S.orders` from snapshot | objects |
| 11 | Fraud / risk | `ix.risk(m)`, `ix.burst(mid)`, `ix.manipulation(mid)`, `ix.dupClusters()` | objects |
| 12 | Recs / growth / commercial | `ix.related(p)`, `ix.similarShops(m)`, `ix.trends()`, `ix.personal(sig)`, `ComparoGrowth(S, ix).opportunities()`, `ComparoCommercial(S, ix, gr).overview()` | ids + scores |

Serialise only numbers/strings/booleans/ids (strip `color`, formatted money, `on*` callbacks,
object references such as `match().product` → `product.id`). Write one JSON file per algorithm with
a header `{ generatedAt: S.NOW, sourceHashes: {file: sha256}, nodeVersion }` so a stale fixture is
detectable when a prototype file changes.

### 13.5 Known parity traps (summary)

1. JS `||` falsy fallbacks in `rank`, `trust`, `priceConfidence` (§0.3, §1.3).
2. `Math.round` half-up-to-+∞ vs PHP `round`; negative deal-score parts and penalties.
3. Stable sort + input order as final tie-breaker (`S.offers` order).
4. `marketMin` built from raw prices incl. anomalies; row totals built from couponed prices.
5. Free-shipping threshold tested on `eff` (row) vs raw price (freeship coupon, `marketStats`,
   orders).
6. Three median definitions and two p90 definitions (§0.3, §10).
7. Two trust formulas (`ix.trust` vs HTML `trustScore`) and two price-stat implementations
   (`ix.histStats`/`priceBadge` vs HTML `priceStats`).
8. Compliance behaviour differs from `COMPLIANCE.md` (§3); the port needs a product decision
   before fixtures are treated as the spec.
9. Seed aliasing: coupon `countries` arrays shared with `m.shipsTo` (§0.2).
10. Formatting (`Intl`) is ICU-dependent — never assert on formatted strings.
