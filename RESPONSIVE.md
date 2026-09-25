# Responsive patterns

Horizontal scroll is a last resort, not a default. Before building any data view, pick its pattern.

| Pattern | Use for | Narrow-screen behaviour |
|---|---|---|
| **A · True data table** | Admin grids with 6+ genuinely tabular columns | Scroll inside the card, edge fade, header and rows share one `min-width` |
| **B · Responsive card list** | Consumer lists: offers, shops, deals | Grid collapses to stacked cards with inline field labels |
| **C · Summary + details** | Offer rows, risk rows, match rows | Primary fields visible, secondary behind a Details toggle |
| **D · Comparison matrix** | Product compare, basket options | One column per entity, row labels in the first column |
| **E · Dense admin grid** | Task queue, audit log | Scroll + (where useful) sticky entity column |
| **F · Timeline** | Automation log, catalogue history, journeys | Stacked, newest first |
| **G · KPI list** | Dashboards | Metric strip, two per row |

Live reference: **`/design-system`** renders every pattern with its rule.

## The shared table classes

Defined once in the document `<helmet>` (media queries cannot be inline):

```
.cmp-t-wrap   the scroll container (also .cmp-scroll for the edge fade)
.cmp-t-inner  the min-width block: header + rows scroll as one unit
.cmp-t-head   column headers — hidden below 721px
.cmp-t-row     grid row — becomes a stacked card below 721px
.cmp-t-lbl    field label — hidden on desktop, shown in the mobile card
.cmp-t-sec    secondary field — hidden on mobile until the row has .is-open
.cmp-t-more   the Details toggle — only rendered below 721px
```

Breakpoint: **720px**. Above it the view is a table; below it a card list.

## Column priority — product offers (pattern C)

Always visible: shop (with rating and trust), **total incl. shipping**, delivery, CTA.
Behind Details: ComparoRank, base price, shipping line, availability and stock confidence.
Total price is always the largest number on the row; the breakdown is secondary.

## Rules that must not be broken

1. A rounded card that clips (`overflow:hidden`) must never contain a grid whose tracks cannot shrink — either make the card the scroller (`overflow-x:auto`) or give the tracks `minmax()` minimums that fit.
2. Header and rows of one table always carry the **same** `min-width`, so they can never desynchronise.
3. Any intentional scroll region must expose all of its content: no `overflow:hidden` ancestor between the row and the scroller.
4. Consumer-facing lists do not scroll horizontally — they collapse.
5. Every scroller shows an affordance: the edge fade is applied automatically to `.cmp-scroll[data-overflowing="1"]`, set by a layout sweep on mount, resize and update.
6. Touch targets are ≥ 44px on controls that appear on mobile.

## Verification

```js
// unreachable-content sweep: no grid may overflow without a scroller ancestor
[...document.querySelectorAll('div')].filter(d => getComputedStyle(d).display === 'grid').filter(d => {
  let p = d.parentElement, scroller = false, clipped = false;
  while (p && p !== document.body) {
    const s = getComputedStyle(p);
    if (s.overflowX === 'auto' || s.overflowX === 'scroll') { scroller = true; break; }
    if (s.overflowX === 'hidden' && p.scrollWidth > p.clientWidth + 2) { clipped = true; break; }
    p = p.parentElement;
  }
  return clipped || (!scroller && d.scrollWidth > d.clientWidth + 2);
}).length; // must be 0
```

Run it at 320, 360, 390, 430, 480, 640, 768, 1024, 1280, 1440 and 1920 px, for the consumer,
merchant and staff views, in both themes. `documentElement.scrollWidth === clientWidth` on every route.
