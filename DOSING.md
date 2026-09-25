# Ingredient dosing — price per gram of active

## Why this exists

Every price comparison in this category compares packs. A 900 g tub against a 1 kg tub, a
90-capsule bottle against a 180-capsule bottle. The number that actually decides value —
what a gram of the substance you are buying costs — was impossible to compute, because the
catalogue linked products to ingredients without amounts.

`seed-dose.js` supplies the missing record: **milligrams of each declared ingredient per
serving**. Everything else is derived.

## The record

```js
p.doses = [{ ingredient: 'Creatine monohydrate', mg: 5000, carrier: false, nrv: null }]
p.doseSource       // 'label_photo' | 'brand_spec' | 'merchant_feed'
p.doseSourceLabel  // human form, rendered on the product page
p.doseUpdated      // when the amounts were last read
```

46 of 46 catalogue products are dosed. A product without `doses` renders its ingredient list
as chips and says plainly that it cannot be priced per gram — an absent amount is stated,
never guessed.

**Amounts that appear in a product's own description are the same number.** "200 mg caffeine
per serving" in `short` and `doses` both read 200. They cannot drift, because the copy was
written to the dose rather than beside it.

## Derived figures

| Figure | Definition | Where |
|---|---|---|
| `servingMg(p)` | pack grams ÷ servings, in mg. Null for capsules and tablets. | product page |
| `activeMg(p)` | sum of non-carrier doses per serving | product page, compare |
| `packActiveG(p)` | `activeMg × servings ÷ 1000` — grams of active in the pack | product page |
| `costPerActiveG(p)` | cheapest deliverable total ÷ `packActiveG` | product page headline, compare row |
| `costPerIngredientG(p, name)` | same, for one substance | ingredient ranking |
| `cheapestSourceOf(name)` | every dosed product carrying the substance, ranked by cost per gram | ingredient page |

### Carriers

`Maltodextrin` and `Cyclic dextrin` are dosed but marked `carrier: true` and excluded from
the active total. Without that, a 4 kg gainer holding 2.4 kg of maltodextrin would rank as the
best value in the catalogue.

### Share of scoop

For powder products the app shows actives as a share of serving weight. This is
**informational, not a judgment**: a pre-workout at 80 % actives and a flavoured nootropic at
5 % are both honest products. The remainder is flavouring, sweetener and anti-caking agents,
and the page says so.

## Market limits

`doseMeta.limits` holds per-substance ceilings by market. `doseLimits(p, iso)` reads the dose
against them, so the warning on a product page is a **consequence of the amount** rather than a
flag stored next to it. Caffeine above 200 mg per serving, melatonin above 1 mg, yohimbine at
all in several markets, green tea extract above 800 mg daily, vitamin D3 above 4000 IU.

## What this unlocks

*Creatine monohydrate*, ranked by what a gram costs:

| | Product | Per gram | Why |
|---|---|---|---|
| 1 | Creatine Monohydrate Micronized | lowest | 200 servings × 5 g |
| 2 | Strength Core | ~1.7× | same substance, smaller pack |
| 3 | Creatine HCl Caps | ~7× | capsule format, different salt |
| 4 | Creatine Gummies | ~10× | format premium |

Same molecule, a tenfold spread. No pack-price comparison shows this, and it is the single
strongest argument the product has for existing.

## Backend notes

`product_ingredient` gains `amount_mg`, `is_carrier`, `nrv_mg`, plus `source` and `read_at` on
the product. `cheapestSourceOf` is a nightly precompute into `ingredient_price_rank` — it walks
every offer of every product carrying the substance and is too heavy for request time. The
prototype memoises it per render against country, currency and offer-override count.
