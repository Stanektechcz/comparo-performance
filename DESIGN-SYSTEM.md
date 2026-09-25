# Design system

## Principles

1. **Data first.** Price, total, rating, availability and delivery are the loudest things on any
   comparison surface.
2. **Fewer boxes.** Group with space, rules and type hierarchy; a card must earn itself
   (it is a discrete, clickable object, or it holds an action).
3. **One accent.** Lime marks the actionable and the decisive number. Everything else is neutral.
4. **Both themes designed, not inverted.** Light is warm off-white with a darker accent for text;
   dark is graphite with lime.

## Semantic tokens

Set on `:root` for dark, `html[data-theme="light"]` for light, and
`@media (prefers-color-scheme: light) html[data-theme="system"]` for system. No component ever
references a raw hex.

```
background      --bg            page canvas
                --bg-blur       sticky header wash
                --bar           bottom bar / compare bar wash
                --overlay       modal scrim
surface         --surface       raised panel
                --surface-2     inset / secondary panel
                --surface-3     raised chip surface
                --surface-sub   subdued band (top strip, footer)
                --input         form field background
                --chip          avatars, placeholders, tag chips
                --chip-2        placeholder stripe partner
text            --text          primary
                --text-2        secondary body
                --text-3        supporting
                --text-4        meta / captions (4.5:1 minimum)
                --text-5        disabled only
border          --line-soft     row separators
                --line          panel borders
                --line-2        interactive borders
accent          --acc           accent fill
                --acc-text      accent text (darkened in light theme)
                --acc-ink       ink on accent fill
                --acc-tint      accent wash / --acc-tint-2 stronger / --acc-line accent border
status          --ok --warn --danger --info  (+ -2 soft, -tint, -line variants)
elevation       --shadow, --focus
spacing         --s1 4 · --s2 8 · --s3 12 · --s4 16 · --s6 24 · --s8 32 · --s12 48 · --s16 64
```

The accent is a runtime tweak: the app writes `--acc`, `--acc-text` (auto-darkened for light) and
`--acc-ink` onto `documentElement`, so a brand-colour change needs no CSS edit.

## Type

- **Archivo** — everything. 900 for display (tight tracking, `-.02` to `-.035em`), 700/800 for
  headings and controls, 400/500 for body.
- **JetBrains Mono** — every number that gets compared: prices, totals, counts, dates, IDs, code.
  A price in a proportional font is a price you cannot scan down a column.
- Display sizes are fluid (`clamp(28px,3.6vw,44px)`); body is 13–16.5px; the smallest meta text is
  10px uppercase mono with letterspacing, on `--text-4` or better.

## Layout

| Context | Width |
| --- | --- |
| App shell | 1440px max, 24px gutters |
| Reading (guides, articles, threads) | 790–850px |
| Data tables | horizontal scroll container with a min-width, cards below 760px |

Grids use `repeat(auto-fit, minmax(N, 1fr))` with `gap`; nothing is positioned absolutely except
overlays, badges and sticky bars.

## Components

Buttons: 38–48px tall, 8–10px radius; primary = accent fill + `--acc-ink`; secondary = 1px
`--line-2` on transparent; destructive = `--danger-line` border with `--danger-2` text. Pills for
filters and tabs (99px radius, accent fill when active). Underline-marked primary nav.
Toggles are 40×22 with a 16px knob. Sentiment bars are three-segment ok/warn/danger.
Placeholders are striped gradients with a mono label naming what belongs there.

## Motion

Two keyframes only: a 6px rise-and-fade for popovers and toasts, and a shimmer for skeletons.
Transitions are 120–180ms ease. Everything is disabled under
`@media (prefers-reduced-motion: reduce)`.

## Accessibility

WCAG 2.2 AA target: 4.5:1 for text (3:1 for headline scale), `:focus-visible` rings at 2px
`--focus` with a 2px offset, 44px minimum touch targets, `aria-label` on icon-only controls,
labelled selects, Escape closing every overlay, and a keyboard command palette (⌘/Ctrl-K, `/`,
`g`-prefixed jumps).

## Prototype implementation

The whole app is one streaming Design Component (`Comparo Performance.dc.html`) with inline
styles referencing the tokens, so a single file paints top-to-bottom without a stylesheet round
trip. Tokens, `@font-face` links, keyframes, the reduced-motion rule and the one mobile media
query are the only global CSS.


## Live reference

**`/design-system`** renders the system from the same primitives the product uses: type scale,
surfaces and colour, spacing scale, button hierarchy, trust/state badges with their meaning, the
seven responsive data patterns, skeletons, the four toast levels, and the prototype state panel
(schema version, storage usage, scoped reset, debug overlay).

## Button hierarchy

| Level | Treatment | Use |
|---|---|---|
| Primary | lime fill, ink text, ≥44px | one per view: Go to shop, Set alert, Create deal, Approve |
| Secondary | 1px line, transparent | Compare, Save, Details, Assign |
| Tertiary | text only, accent colour | Why this rank?, Why am I seeing this?, Full methodology |
| Destructive | danger line + danger text | Suspend, Hide, Exclude, Reset demo data |
| Icon | 44×44 hit area, `aria-label` **and** `title` | theme, palette, notifications, quantity ±, close |

## Badge semantics

Lime marks Comparo-owned value (Partner, Comparo exclusive). Green marks verified trust (Verified,
Verified purchase). Blue marks provenance (Official response). Amber marks caution
(Price under review, Market status not verified, Stale feed). Red marks failure (Invalid, Suspended).
**Sponsored is deliberately the quietest badge on the page** — a neutral outline, never lime — because
paid placement must be obvious without being rewarded.

## Data typography

Prices, scores and analytics use JetBrains Mono with `font-variant-numeric: tabular-nums` so columns
align and digits do not jitter between renders. Totals are always the largest number in a row;
component prices are secondary.

## Component rules

1. No card inside a card inside a card. Two levels of surface maximum per region.
2. Group with spacing and typography before reaching for a border.
3. Repetition belongs in data, not markup: build rows from arrays, never by duplicating markup.
4. Any new data view must declare its responsive pattern from RESPONSIVE.md before it is built.
5. Every score shown to a user must have an explanation control next to it.
