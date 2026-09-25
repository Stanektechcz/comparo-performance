# Design system map: prototype to Laravel 13 + Inertia 3 + React 19 + Tailwind 4

Source of truth: `Comparo Performance.dc.html` (abbreviated **DC** below; line numbers refer to it),
`intel.js`, `labels.js`, `seed.js`, and the docs `DESIGN-SYSTEM.md`, `RESPONSIVE.md`,
`ACCESSIBILITY.md`, `PERFORMANCE.md`, `COMPARORANK.md`, `COMPLIANCE.md`.

How the prototype is built: DC is a single "Design Component". Markup is a template (`<sc-if>`,
`<sc-for>`, `{{ }}` bindings, `style-hover="…"` for hover styles) with **inline styles that reference
CSS custom properties**. Only the tokens, keyframes, a handful of `cmp-*` utility classes and the
media queries live in the one global `<style>` block (DC 41–176). All view data is produced by a
class `Component extends DCLogic` (DC 11679–20492): `renderVals()` (DC 15477) merges the shell
values with one `build<Route>()` result per hash route.

Conventions in this document:

- "verified" = read directly in the source at the cited line.
- "unverified" = inferred, not confirmed by reading the code path end to end.
- Every hex/rgba value is copied verbatim from DC 42–86.

---

## 1. Design tokens

### 1.1 Theme mechanism (verified)

| Aspect | Prototype behaviour | Source |
| --- | --- | --- |
| Selector for dark | `:root, html[data-theme="dark"]`. Dark is the **default**: with no attribute, `:root` gets dark values | DC 42 |
| Selector for light | `html[data-theme="light"]` | DC 57 |
| System mode | `@media (prefers-color-scheme: light) { html[data-theme="system"] { …light values… } }` | DC 71–86 |
| Attribute writer | `applyTheme()` runs `document.documentElement.setAttribute('data-theme', state.theme)` on mount and on every update | DC 11855–11869, 11872, 11926 |
| `system` in practice | `applyTheme()` immediately converts `theme: 'system'` into `'dark'` or `'light'` via `matchMedia('(prefers-color-scheme: dark)')` and `setState`. `data-theme="system"` is therefore never written, so the system CSS block and the `matchMedia` change listener (DC 11901–11905) are effectively dead code | DC 11856–11859 |
| Toggle | Icon button in the utility row. `cycleTheme` flips between `light` and `dark` only, with no system option in the UI. Icon: `☾` when light, `☀` otherwise. Title/aria-label: "Switch to dark theme" / "Switch to light theme" | DC 220, 15518–15520 |
| Initial value | `props.defaultTheme` (editor prop, default `"dark"`, options `dark/light/system`) | DC 11678, 11710 |
| Persistence | Not a dedicated key. `theme` is one field inside the JSON blob under `localStorage['comparo.proto.v2']` (plus `comparo.state.version` = `3`), written by a 220 ms debounced `persistNow()` | DC 11736, 11828–11842 |
| Runtime accent override | `applyTheme()` also writes **inline** on `<html>`: `--acc` = `props.accent` (default `#C8FF3D`; options `#C8FF3D`, `#FF6A3D`, `#5CE1E6`, `#FFC93D`), `--acc-text` = accent in dark, or `darken(accent, 0.55)` in light, `--acc-ink` = `#10140A` (light) or `#08090B` (dark) | DC 11864–11867, 11843–11849 |

**Consequence of the inline override (verified by calculation):** at runtime in light theme the
stylesheet values `--acc:#C2F52E` and `--acc-text:#4A6B00` are **overridden** by the inline values
`--acc:#C8FF3D` and `--acc-text:#5A731B` (that is, `#C8FF3D` with each channel multiplied by 0.45:
200→90, 255→115, 61→27). The screenshots therefore show `#5A731B` accent text in light mode, not `#4A6B00`.
Decision for the port: either keep the brand-tweak mechanism (write the three properties server-side
into `<html style>`), or drop it and use the stylesheet values. **Recommended:** use the stylesheet
values (`#4A6B00` gives higher contrast) and treat the accent picker as a prototype-only editor prop.

**FOUC note for the port:** the prototype sets `data-theme` only after mount, so the first paint is
always dark. In Laravel, render `data-theme` on `<html>` in `app.blade.php` (from a cookie), and add a
blocking inline script that reads the stored preference before paint.

### 1.2 Colour tokens (exact values)

| Token | Role (DESIGN-SYSTEM.md) | Dark (`:root`, `[data-theme="dark"]`) | Light (`[data-theme="light"]`) |
| --- | --- | --- | --- |
| `--bg` | page canvas | `#08090B` | `#FBFAF7` |
| `--bg-blur` | sticky header wash | `rgba(8,9,11,.88)` | `rgba(251,250,247,.9)` |
| `--bar` | bottom bar / compare bar wash | `rgba(11,13,16,.95)` | `rgba(255,255,255,.96)` |
| `--overlay` | modal scrim | `rgba(4,5,7,.74)` | `rgba(24,24,22,.42)` |
| `--surface` | raised panel | `#0E1116` | `#FFFFFF` |
| `--surface-2` | inset / secondary panel | `#0B0E12` | `#F5F4F0` |
| `--surface-3` | raised chip surface | `#151920` | `#EFEEE9` |
| `--surface-sub` | subdued band (top strip, footer) | `#0B0D10` | `#F5F4F0` |
| `--input` | form field bg | `#111419` | `#FFFFFF` |
| `--chip` | avatars, placeholders, tags, bar tracks | `#1B1F26` | `#E9E8E2` |
| `--chip-2` | placeholder stripe partner | `#15181D` | `#F2F1EC` |
| `--text` | primary | `#EDF0F3` | `#15171B` |
| `--text-2` | secondary body | `#C3CAD2` | `#3B424B` |
| `--text-3` | supporting | `#98A0AA` | `#5A616B` |
| `--text-4` | meta / captions (4.5:1 min) | `#828A94` | `#666D77` |
| `--text-5` | disabled only (also the chart avg line) | `#5C646E` | `#9BA1AA` |
| `--line-soft` | row separators | `rgba(255,255,255,.055)` | `rgba(20,22,26,.07)` |
| `--line` | panel borders | `rgba(255,255,255,.08)` | `rgba(20,22,26,.11)` |
| `--line-2` | interactive borders | `rgba(255,255,255,.13)` | `rgba(20,22,26,.18)` |
| `--acc` | accent fill | `#C8FF3D` | `#C2F52E` (runtime inline: `#C8FF3D`) |
| `--acc-text` | accent text | `#C8FF3D` | `#4A6B00` (runtime inline: `#5A731B`) |
| `--acc-ink` | ink on accent fill | `#08090B` | `#10140A` |
| `--acc-2` | soft accent text (used once, auth-modal note DC 11339) | `#D9F5A0` | `#3F5C00` |
| `--acc-tint` | accent wash | `rgba(200,255,61,.07)` | `rgba(160,215,20,.14)` |
| `--acc-tint-2` | stronger wash (Partner badge) | `rgba(200,255,61,.15)` | `rgba(160,215,20,.24)` |
| `--acc-line` | accent border | `rgba(200,255,61,.32)` | `rgba(120,165,10,.4)` |
| `--ok` | success / verified | `#35D07F` | `#12784A` |
| `--ok-tint` | | `rgba(53,208,127,.13)` | `rgba(18,120,74,.1)` |
| `--ok-line` | | `rgba(53,208,127,.34)` | `rgba(18,120,74,.3)` |
| `--warn` | caution | `#FFB020` | `#8A5A00` |
| `--warn-2` | caution text on tint | `#FFD08A` | `#6B4700` |
| `--warn-tint` | | `rgba(255,176,32,.1)` | `rgba(196,132,0,.12)` |
| `--warn-line` | | `rgba(255,176,32,.32)` | `rgba(150,100,0,.3)` |
| `--danger` | failure | `#FF5A4E` | `#C0342A` |
| `--danger-2` | danger text on tint | `#FF9A90` | `#A82A20` |
| `--danger-tint` | | `rgba(255,90,78,.12)` | `rgba(192,52,42,.09)` |
| `--danger-line` | | `rgba(255,90,78,.36)` | `rgba(192,52,42,.3)` |
| `--info` | provenance | `#6FA8FF` | `#1F5FC0` |
| `--info-2` | info text on tint | `#9EC5FF` | `#17489A` |
| `--info-tint` | | `rgba(111,168,255,.13)` | `rgba(31,95,192,.09)` |
| `--info-line` | | `rgba(111,168,255,.32)` | `rgba(31,95,192,.3)` |
| `--shadow` | shadow colour | `rgba(0,0,0,.6)` | `rgba(30,30,28,.16)` |
| `--focus` | focus ring | `rgba(200,255,61,.6)` | `rgba(90,130,0,.55)` |

Not a token but part of the contract: `--tap` = `auto`, becoming `44px` under
`@media (hover:none),(max-width:760px)` (DC 88–89). It is used as `min-height:var(--tap)` on links
that must meet 44 px on touch (27 usages).

### 1.3 Score, band and status colour mapping (no dedicated tokens; scores map onto status tokens)

| Scale | Thresholds → label → colour | Source |
| --- | --- | --- |
| **ComparoRank** (0–100) | ≥90 Exceptional `--ok` · ≥80 Excellent `--acc-text` · ≥70 Good `--text-2` · ≥60 Fair `--warn` · <60 Low confidence `--danger` | intel.js 599–600 |
| **Merchant trust** (`ix.trust`, shown as "trust N/100" in offer rows) | ≥90 Highly trusted, ≥78 Trusted `--ok` · ≥65 Generally reliable `--acc-text` · ≥50 Mixed signals `--warn` · <50 Low trust `--danger` | intel.js 81–82 |
| Legacy `trustScore()` (trust modal) | ≥85 Excellent `--ok` · ≥70 Good `--acc-text` · ≥55 Mixed `--warn` · else Weak `--danger` | DC 11996 |
| **Price confidence** | ≥88 High `--ok` · ≥70 Moderate `--acc-text` · ≥50 Low `--warn` · else Unreliable `--danger` | DC 13252–13253 |
| **Stock** | In stock `--ok` · Low stock `--warn` · Pre-order `--info` · Unknown (feed >48 h) `--text-3` · Out of stock `--danger` | intel.js 517–524 |
| **Price badge** (Buying intelligence) | Exceptional price `--ok` · Good price `--acc-text` · Typical price `--text-2` · Above average `--warn` | intel.js 352–357 |
| **Price badge** (Price intelligence, `priceStats`) | All-time low / Near historical low / Good price `--ok` · Average price `--warn` · Above average `--danger` | DC 11964–11968 |
| **Deal timing** | Strong `--ok` · Reasonable `--acc-text` · Worth waiting `--warn` · Poor `--danger` | intel.js 359–364 |
| **Trend** | Downward `--ok` · Upward `--danger` · Highly volatile `--warn` · Likely stable `--text-2` | intel.js 371–374 |
| **Coupon state** | Verified / Merchant verified `--ok` · Community verified `--acc-text` · Unverified `--text-3` · Expired `--text-4` · Invalid `--danger` | intel.js 460–467 |
| **Compliance** | allowed `--ok` · restricted `--warn` · prescription_only `--info` · not_allowed `--danger` · unknown `--warn` (each with `-tint` bg and `-line` border) | DC 15850–15856 |
| **Price change** (chart, vs 90 d) | up `--danger` · down `--ok` · flat `--text-3` | DC 13570, 11972 |
| Sentiment bar | three segments `--ok` / `--warn` / `--danger` | DC 1331–1335 |

### 1.4 Typography (verified)

Google Fonts (DC 13–15):

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
```

| Family | Weights loaded | Stack as written | Use |
| --- | --- | --- | --- |
| Archivo | 400, 500, 600, 700, 800, 900 (roman only) | `Archivo,'Helvetica Neue',Helvetica,sans-serif` (body, DC 96) | everything textual |
| JetBrains Mono | 400, 500, 700 (600 is used inline, e.g. `font:600 12px/1 'JetBrains Mono'` ×88, but **is not loaded**, so the browser synthesises or falls back to 500/700; unverified which) | `'JetBrains Mono',monospace` | every compared number: prices, totals, counts, dates, IDs, code, micro-labels |

Numerals: `font-variant-numeric: tabular-nums` is set explicitly in 39 places (row total DC 993, dose
amounts DC 1216, per-gram DC 1265, design-system "Data value"). All other numbers rely on the
monospaced family. **Port rule:** put `tabular-nums` on the mono utility itself.

Type scale as rendered on `/design-system` (DC 13686–13695) and observed usage:

| Role | Spec | Where |
| --- | --- | --- |
| Hero display | `900 clamp(38px,5.2vw,66px)/0.98`, `letter-spacing:-.035em`, `text-wrap:balance` | home h1, DC 384 |
| Display (page h1) | `900 clamp(30px,3.6vw,42px)/1.05`, `-.03em`. Variants: `clamp(28px,3.4vw,42px)/1.05` (product h1 DC 823), `clamp(30px,4vw,44px)` ×12, `clamp(28px,3.4vw,40px)` ×10 | route headers |
| H1 fixed | `900 28px/1.1`, `-.03em` | |
| H2 section | `900 22px/1.1`, `-.02em`; major sections `900 26px/1.1` ("Where to buy", "Reviews"); also `900 21px/1.1` | product sections |
| Modal title | `900 20px/1.1`, `-.02em` | DC 11371 etc. |
| H3 card | `800 16px/1.2` | "Price alert" DC 1165 |
| Body | `400 14.5px/1.6` on `--text-2`; hero lede `16.5px/1.55` on `--text-3` | |
| Small | `400 12.5px/1.5` on `--text-3` | |
| Eyebrow / label | `800 9.5px/1`, `.12em`, uppercase, `--text-4` (also `800 10px/1` ×170, `.11em` ×379 most common) | stat labels |
| Badge text | `700 9px/1.6`, `.06em`, uppercase (row badges); `800 10px/1.6`, `.07em` (Best value) | DC 961–969, 920 |
| Data value | `700 17px/1` JetBrains Mono, tabular-nums | |
| Key price | best total `700 34px/1` mono `--acc-text` (DC 849); row total `700 20px/1` mono `--acc-text` tabular (DC 993); rating big `700 44px/1` mono (DC 1343) | |
| Mono meta | `500 11px/1` and `500 10.5px/1` mono, `--text-4` | timestamps, sources |
| Brand wordmark | `COMPARO` `900 20px/1` uppercase `-.02em` + `performance` `700 10px/1` mono `--acc-text` `.14em` uppercase | DC 228–229 |

### 1.5 Spacing, radii, shadows, z-index, motion, breakpoints

**Spacing tokens** (DC 55, dark block only; they are theme-independent):
`--s1:4px --s2:8px --s3:12px --s4:16px --s6:24px --s8:32px --s12:48px --s16:64px`.
They are **never referenced** via `var(--sN)` in DC; inline styles use raw px. Most common gaps:
10px (×324), 12 (×163), 8 (×132), 14 (×112), 16 (×89), 6, 7, 9, 20, 5, 11, 24. Section rhythm on
the product page: `padding:40px 0 0` (44px for the last two). App shell gutter `24px`, max width
`1440px`. **Port:** adopt Tailwind's 4px base (`--spacing: 4px` default); half-steps (6, 7, 9, 11,
13 px) should be normalised to the 4/8 grid unless pixel parity is required.

**Radii** (by frequency): `99px` pill (×242), `16px` large panel (×236), `14px` card (×191), `10px`
button (×159), `9px` button/input (×146), `7px` small (×140), `8px` (×101), `12px` (×67), `11px`,
`18px` modal (×18), `13px` popover, `6px` focus ring, `20px` bottom sheet top corners.
Suggested token set: `--radius-xs:7px --radius-sm:9px --radius-md:10px --radius-lg:14px
--radius-xl:16px --radius-2xl:18px --radius-pill:99px`.

**Shadows** (all use `var(--shadow)`):

| Use | Value |
| --- | --- |
| Popover / dropdown / notification / user menu | `0 24px 60px var(--shadow)` |
| Market picker menu | `0 22px 54px var(--shadow)` |
| Hero search suggestions | `0 30px 70px var(--shadow)` |
| Modal / command palette | `0 40px 90px var(--shadow)` |
| Toast | `0 20px 50px var(--shadow)` |
| Mobile bottom sheet | `0 -30px 70px var(--shadow)` |
| Active nav underline (not a shadow semantically) | `inset 0 -3px 0 0 var(--acc)` |

**Z-index scale** (verified, all used values):

| z | Element |
| --- | --- |
| 40 | search suggestion dropdowns |
| 60 | sticky `<header>`; mobile nav bottom sheet `.cmp-navpanel[data-sheet="1"]` |
| 70 | compare bar (fixed bottom); notification popover; user menu |
| 75 | utility-row picker menus |
| 80 | mobile bottom nav |
| 90 | toast |
| 95 | consent banner |
| 100 | modal dialogs (all three overlay containers) |
| 110 | command palette |

**Motion:** keyframes `cmpIn` (6px rise + fade; `.12s`/`.14s ease` on popovers and modals),
`cmpToast` (12px rise; `.16s ease`), `cmpSheet` (18px rise, opacity .4→1; `.2s
cubic-bezier(.2,.8,.3,1)`), `cmpShimmer` (skeleton; `1.2s linear infinite`), `cmpPulse` (live dot;
`2s infinite`). Transitions: body `background-color .18s ease, color .18s ease`; toggle knob
`transform .16s ease`. `@media (prefers-reduced-motion: reduce){*{animation-duration:.001ms
!important;transition-duration:.001ms !important}}` (DC 101).

**Breakpoints** (every media query in DC):

| Query | Effect |
| --- | --- |
| `(hover:none),(max-width:760px)` | `--tap:44px` |
| `(max-width:760px)` | mobile bottom nav shown; primary nav becomes a single scrolling row with right edge fade; utility row wraps; nav panel scrolls; `body{padding-bottom:72px}`; toast lifted to `bottom:calc(84px + env(safe-area-inset-bottom))` |
| `(max-width:900px)` | `.cmp-utilrow{align-items:flex-start}` |
| `(min-width:721px)` | scroll edge fade on `.cmp-scroll[data-overflowing="1"]` |
| `(max-width:720px)` | `.cmp-t-*` table becomes a card list (see §5.2) |
| `(max-width:430px)` | every inline `repeat(auto-fit…)`/`repeat(auto-fill…)` grid and `grid-template-columns:200px/180px` grid is forced to `1fr` |
| `print` | hides header/nav/footer/`[data-noprint]`, print-document rules |

Suggested Tailwind 4 breakpoints: `--breakpoint-xs:431px; --breakpoint-sm:721px;
--breakpoint-md:761px; --breakpoint-lg:901px` (mobile-first inversions of the max-width queries).

### 1.6 Ready-to-paste CSS (exact prototype tokens + global rules)

Put this in `resources/css/tokens.css` and import it before Tailwind's `@theme` mapping. It keeps
the prototype selectors verbatim, so `data-theme` on `<html>` stays the switch.

```css
/* ==== Comparo tokens: copied verbatim from Comparo Performance.dc.html lines 42-89 ==== */
:root, html[data-theme="dark"] {
  --bg:#08090B; --bg-blur:rgba(8,9,11,.88); --bar:rgba(11,13,16,.95); --overlay:rgba(4,5,7,.74);
  --surface:#0E1116; --surface-2:#0B0E12; --surface-3:#151920; --surface-sub:#0B0D10;
  --input:#111419; --chip:#1B1F26; --chip-2:#15181D;
  --text:#EDF0F3; --text-2:#C3CAD2; --text-3:#98A0AA; --text-4:#828A94; --text-5:#5C646E;
  --line-soft:rgba(255,255,255,.055); --line:rgba(255,255,255,.08); --line-2:rgba(255,255,255,.13);
  --acc:#C8FF3D; --acc-text:#C8FF3D; --acc-ink:#08090B; --acc-2:#D9F5A0;
  --acc-tint:rgba(200,255,61,.07); --acc-tint-2:rgba(200,255,61,.15); --acc-line:rgba(200,255,61,.32);
  --ok:#35D07F; --ok-tint:rgba(53,208,127,.13); --ok-line:rgba(53,208,127,.34);
  --warn:#FFB020; --warn-2:#FFD08A; --warn-tint:rgba(255,176,32,.1); --warn-line:rgba(255,176,32,.32);
  --danger:#FF5A4E; --danger-2:#FF9A90; --danger-tint:rgba(255,90,78,.12); --danger-line:rgba(255,90,78,.36);
  --info:#6FA8FF; --info-2:#9EC5FF; --info-tint:rgba(111,168,255,.13); --info-line:rgba(111,168,255,.32);
  --shadow:rgba(0,0,0,.6); --focus:rgba(200,255,61,.6);
  --s1:4px; --s2:8px; --s3:12px; --s4:16px; --s6:24px; --s8:32px; --s12:48px; --s16:64px;
}
html[data-theme="light"] {
  --bg:#FBFAF7; --bg-blur:rgba(251,250,247,.9); --bar:rgba(255,255,255,.96); --overlay:rgba(24,24,22,.42);
  --surface:#FFFFFF; --surface-2:#F5F4F0; --surface-3:#EFEEE9; --surface-sub:#F5F4F0;
  --input:#FFFFFF; --chip:#E9E8E2; --chip-2:#F2F1EC;
  --text:#15171B; --text-2:#3B424B; --text-3:#5A616B; --text-4:#666D77; --text-5:#9BA1AA;
  --acc:#C2F52E; --acc-text:#4A6B00; --acc-ink:#10140A; --acc-2:#3F5C00;
  --acc-tint:rgba(160,215,20,.14); --acc-tint-2:rgba(160,215,20,.24); --acc-line:rgba(120,165,10,.4);
  --ok:#12784A; --ok-tint:rgba(18,120,74,.1); --ok-line:rgba(18,120,74,.3);
  --warn:#8A5A00; --warn-2:#6B4700; --warn-tint:rgba(196,132,0,.12); --warn-line:rgba(150,100,0,.3);
  --danger:#C0342A; --danger-2:#A82A20; --danger-tint:rgba(192,52,42,.09); --danger-line:rgba(192,52,42,.3);
  --info:#1F5FC0; --info-2:#17489A; --info-tint:rgba(31,95,192,.09); --info-line:rgba(31,95,192,.3);
  --line-soft:rgba(20,22,26,.07); --line:rgba(20,22,26,.11); --line-2:rgba(20,22,26,.18);
  --shadow:rgba(30,30,28,.16); --focus:rgba(90,130,0,.55);
}
@media (prefers-color-scheme: light) {
  html[data-theme="system"] {
    --bg:#FBFAF7; --bg-blur:rgba(251,250,247,.9); --bar:rgba(255,255,255,.96); --overlay:rgba(24,24,22,.42);
    --surface:#FFFFFF; --surface-2:#F5F4F0; --surface-3:#EFEEE9; --surface-sub:#F5F4F0;
    --input:#FFFFFF; --chip:#E9E8E2; --chip-2:#F2F1EC;
    --text:#15171B; --text-2:#3B424B; --text-3:#5A616B; --text-4:#666D77; --text-5:#9BA1AA;
    --acc:#C2F52E; --acc-text:#4A6B00; --acc-ink:#10140A; --acc-2:#3F5C00;
    --acc-tint:rgba(160,215,20,.14); --acc-tint-2:rgba(160,215,20,.24); --acc-line:rgba(120,165,10,.4);
    --ok:#12784A; --ok-tint:rgba(18,120,74,.1); --ok-line:rgba(18,120,74,.3);
    --warn:#8A5A00; --warn-2:#6B4700; --warn-tint:rgba(196,132,0,.12); --warn-line:rgba(150,100,0,.3);
    --danger:#C0342A; --danger-2:#A82A20; --danger-tint:rgba(192,52,42,.09); --danger-line:rgba(192,52,42,.3);
    --info:#1F5FC0; --info-2:#17489A; --info-tint:rgba(31,95,192,.09); --info-line:rgba(31,95,192,.3);
    --line-soft:rgba(20,22,26,.07); --line:rgba(20,22,26,.11); --line-2:rgba(20,22,26,.18);
    --shadow:rgba(30,30,28,.16); --focus:rgba(90,130,0,.55);
  }
}
:root{--tap:auto}
@media (hover:none),(max-width:760px){ :root{--tap:44px} }

/* ==== global base (DC 87-128), kept 1:1 ==== */
*{box-sizing:border-box}
html,body{overflow-wrap:break-word}
div,span,a,p,h1,h2,h3,td,th,li,label{min-width:0}
pre,code{overflow-wrap:anywhere;white-space:pre-wrap}
img,svg,video,canvas{max-width:100%}
html,body{margin:0;padding:0;background:var(--bg);color:var(--text);font-family:Archivo,'Helvetica Neue',Helvetica,sans-serif;-webkit-font-smoothing:antialiased;transition:background-color .18s ease,color .18s ease}
a{color:var(--text);text-decoration:none}
a:hover{color:var(--acc-text)}
input,select,textarea,button{font-family:inherit}
input:focus-visible,select:focus-visible,textarea:focus-visible,button:focus-visible,a:focus-visible{outline:2px solid var(--focus);outline-offset:2px;border-radius:6px}
@media (prefers-reduced-motion: reduce){*{animation-duration:.001ms !important;transition-duration:.001ms !important}}
::selection{background:var(--acc);color:var(--acc-ink)}
::-webkit-scrollbar{width:10px;height:10px}
::-webkit-scrollbar-thumb{background:var(--chip);border-radius:8px}
::-webkit-scrollbar-track{background:var(--surface-2)}
@keyframes cmpIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
@keyframes cmpToast{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
@keyframes cmpPulse{0%,100%{opacity:1}50%{opacity:.35}}
@keyframes cmpShimmer{from{background-position:200% 0}to{background-position:-200% 0}}
@keyframes cmpSheet{from{transform:translateY(18px);opacity:.4}to{transform:none;opacity:1}}
.cmp-skel{background:linear-gradient(90deg,var(--surface-2) 0%,var(--surface-3) 50%,var(--surface-2) 100%);background-size:200% 100%;animation:cmpShimmer 1.2s linear infinite;border-radius:8px}
```

Recommended Tailwind 4 bridge (`resources/css/app.css`; this is a recommendation, not in the prototype):

```css
@import "tailwindcss";
@import "./tokens.css";
@custom-variant dark (&:where([data-theme=dark], [data-theme=dark] *));
@theme inline {
  --font-sans: Archivo, 'Helvetica Neue', Helvetica, sans-serif;
  --font-mono: 'JetBrains Mono', ui-monospace, monospace;
  --color-bg: var(--bg); --color-surface: var(--surface); --color-surface-2: var(--surface-2);
  --color-surface-3: var(--surface-3); --color-surface-sub: var(--surface-sub); --color-input: var(--input);
  --color-chip: var(--chip); --color-chip-2: var(--chip-2);
  --color-fg: var(--text); --color-fg-2: var(--text-2); --color-fg-3: var(--text-3); --color-fg-4: var(--text-4); --color-fg-5: var(--text-5);
  --color-line-soft: var(--line-soft); --color-line: var(--line); --color-line-2: var(--line-2);
  --color-acc: var(--acc); --color-acc-text: var(--acc-text); --color-acc-ink: var(--acc-ink);
  --color-acc-tint: var(--acc-tint); --color-acc-tint-2: var(--acc-tint-2); --color-acc-line: var(--acc-line);
  --color-ok: var(--ok); --color-ok-tint: var(--ok-tint); --color-ok-line: var(--ok-line);
  --color-warn: var(--warn); --color-warn-2: var(--warn-2); --color-warn-tint: var(--warn-tint); --color-warn-line: var(--warn-line);
  --color-danger: var(--danger); --color-danger-2: var(--danger-2); --color-danger-tint: var(--danger-tint); --color-danger-line: var(--danger-line);
  --color-info: var(--info); --color-info-2: var(--info-2); --color-info-tint: var(--info-tint); --color-info-line: var(--info-line);
  --color-overlay: var(--overlay); --color-bar: var(--bar); --color-bg-blur: var(--bg-blur); --color-focus: var(--focus);
  --radius-xs: 7px; --radius-sm: 9px; --radius-md: 10px; --radius-lg: 14px; --radius-xl: 16px; --radius-2xl: 18px; --radius-pill: 99px;
  --shadow-pop: 0 24px 60px var(--shadow); --shadow-modal: 0 40px 90px var(--shadow); --shadow-toast: 0 20px 50px var(--shadow);
  --breakpoint-xs: 431px; --breakpoint-sm: 721px; --breakpoint-md: 761px; --breakpoint-lg: 901px;
}
```

shadcn/ui: import only the unstyled Radix-based behaviour (Dialog, Popover, DropdownMenu, Select,
Tabs, Tooltip, Toggle). Strip shadcn's default `--background/--primary/...` token file entirely, or
alias those names to the Comparo tokens, so no shadcn default colour, radius or ring appears.

---

## 2. Component patterns

All components are inline-styled in DC. The only shared class names are `cmp-utilrow`,
`cmp-navpanel`, `cmp-scroll`, `cmp-t-wrap`, `cmp-t-inner`, `cmp-t-head`, `cmp-t-row`, `cmp-t-lbl`,
`cmp-t-sec`, `cmp-t-more`, `is-open`, `cmp-sel`, `cmp-toast`, `cmp-skel`, plus the data attributes
`data-overflowing`, `data-sheet`, `data-noprint`, `data-print-doc`, `data-print-only`.

### 2.1 Buttons (DC 13696–13701, DESIGN-SYSTEM.md "Button hierarchy")

| Variant | Exact style | States / notes |
| --- | --- | --- |
| Primary | `padding:12px 17px` (product: `13px 17px`), `border-radius:10px`, `border:0`, `background:var(--acc)`, `color:var(--acc-ink)`, `font:800 13px/1 Archivo`, `min-height:44px` | One per view. Modal submit: `padding:14px`, `font:800 13.5px/1`, full width |
| Secondary | same box, `border:1px solid var(--line-2)`, transparent bg, `color:var(--text)`, `font:700 13px/1` | Save / Watch / Compare / Basket |
| Tertiary (text link-button) | `padding:0` (product) or `12px 6px`, `border:0`, transparent bg, `color:var(--acc-text)`, `font:700 10.5–13px/1` | "Why this rank?", "Why am I seeing this?", "Full methodology →" |
| Destructive | `border:1px solid var(--danger-line)`, `color:var(--danger)` (or `--danger-2` on `--danger-tint` bg in the delete modal) | |
| Disabled | `border:1px solid var(--line)`, `color:var(--text-5)`, `cursor:not-allowed` | no `disabled` attribute in the showcase: **gap** |
| Icon | 34×34 (utility row, `border-radius:9px`, `border:1px solid var(--line-2)`, `background:var(--input)`) or 38×38 (header, transparent). Must carry `aria-label` + `title` | Theme, ⌘K, notifications `◔`, saved `★` |
| Accent-soft CTA | `border:1px solid var(--acc-line)`, `background:var(--acc-tint)`, `color:var(--acc-text)`, `font:800 12px/1`, 38px high | "Ask Comparo" header link, DC 268 |
| Pill / chip / filter | `padding:10px 14px`, `border-radius:99px`, `font:700 12px/1`, `min-height:40px`; off: `border:1px solid var(--line-2)`, transparent, `--text-2`; on: `bg/border var(--acc)`, `color var(--acc-ink)`; `aria-pressed` | DC 905, 16011 |
| Active-filter chip (removable) | `padding:6px 10px`, pill, `border:1px solid var(--acc-line)`, `background:var(--acc-tint)`, `color:var(--acc-text)`, `font:700 11.5px/1`, trailing `×` (`aria-hidden`), `aria-label="Remove filter …"` | DC 912 |
| Segmented range | `padding:7px 11px`, `border-radius:7px`, `1px solid var(--line-2)`, mono `700 11.5px/1`; active: `--acc` bg + `--acc-ink` | chart ranges DC 1122 (no `aria-pressed`: **gap**) |
| Toggle switch | 40×22 pill, knob 16×16 `var(--bg)` at `top:3px;left:3px`, `transform` slide `.16s` | DC 2427–2429 (no `role="switch"`/`aria-checked`: **gap**) |
| Select | `padding:10px 11px`, `border-radius:9px`, `1px solid var(--line-2)`, `background:var(--input)`, `font:600 12px/1`. `.cmp-sel` variant removes the native arrow and hovers to `--chip` | product selects DC 894–898 (unlabelled: **gap**) |

Hover: global `a:hover{color:var(--acc-text)}`; menu rows use `style-hover="background:var(--surface-2|3)"`.
Focus: global `:focus-visible` ring. There is no pressed/active visual except the accent fill toggle.

### 2.2 Badges (DC 13702–13712; DESIGN-SYSTEM.md "Badge semantics")

Base: `padding:2px 6px`, `border-radius:99px`, `font:700 9px/1.6 Archivo`, `letter-spacing:.06em`,
uppercase (offer-row size); larger variant `padding:3px 9px`, `font:800 10px/1.6`.

| Badge | bg | fg | border | Meaning |
| --- | --- | --- | --- | --- |
| Verified | `--ok-tint` | `--ok` | none | business verification confirmed |
| Verified purchase | `--ok-tint` | `--ok` | none | review backed by an order (`9.5px`, DC 1372) |
| Partner | `--acc-tint-2` | `--acc-text` | none | commercial relationship, no ranking effect |
| Comparo exclusive | `--acc-tint-2` | `--acc-text` | none | negotiated offer |
| Best value | `--acc` | `--acc-ink` | none | top eligible offer (`800 10px/1.6`, `.07em`, DC 920) |
| **Sponsored** | transparent | `--text-3` | `1px solid var(--line-2)` | paid placement; **deliberately the quietest badge, never lime** |
| Official response | `--info-tint` | `--info` | | merchant reply |
| Price under review (anomaly) | `--warn-tint` | `--warn-2` | | anomaly held from Best value |
| Reference price unverified (fake discount) | `--warn-tint` | `--warn-2` | | DC 969 |
| Market status not verified | `--warn-tint` | `--warn-2` | | compliance `unknown` |
| Stale feed | transparent | `--text-4` | `1px solid var(--line-2)` | offer >48 h old (DC 968) |
| Invalid | `--danger-tint` | `--danger` | | coupon reported as not working |
| Free shipping | `--ok-tint` | `--ok` | | deal cards DC 1531 |
| Product/shop **labels** (earned) | transparent | label tone colour | `1px solid <tone>` | tones from labels.js 14: `acc`→`--acc-text`, `ok`→`--ok`, `info`→`--info`, `warn`→`--warn`; link to `#/labels`; `title` = "why — notClaim" (DC 827) |
| Compliance "restricted" etc. | full banner, not a badge; see §3.3 | | | |
| Menu badge (e.g. "Free", "Apply") | `--acc` | `--acc-ink` | | `800 9px/1.6`, `.07em` (DC 359) |

### 2.3 Cards and panels

| Pattern | Style |
| --- | --- |
| Panel / card | `background:var(--surface)`, `border:1px solid var(--line)`, `border-radius:16px` (large) or `14px` (list card), padding `20px` / `16–18px` |
| Inset panel | `background:var(--surface-2)`, `border:1px solid var(--line)`, `border-radius:11–14px` |
| KPI tile (Buying intelligence) | surface card r14, `padding:17px 18px`; eyebrow `700 9.5px` `.12em` `--text-4`; value mono 22–26px or Archivo `800 17px` in the score colour; 5px bar on `--chip` |
| Spec grid ("hairline grid") | container `display:grid; gap:1px; background:var(--line); border:1px solid var(--line); border-radius:12px; overflow:hidden`; cells `background:var(--surface-2); padding:12px 14px` (DC 875–881) |
| Notice / banner | `border:1px solid var(--<status>-line)`, `background:var(--<status>-tint)`, `border-radius:11–14px`, text in `--<status>-2` or `--<status>` |
| Accent callout | `--acc-tint` bg + `--acc-line` border, eyebrow in `--acc-text` |
| Image placeholder | `repeating-linear-gradient(135deg,var(--chip) 0 10px,var(--chip-2) 10px 20px)` with a centred mono `600 11px/1` `--text-4` label naming the missing asset |
| Product card | surface r14 `padding:14px`; 96px placeholder; brand mono `600 10px` uppercase; name `700 13.5px/1.3`; price mono `700 16px` `--acc-text`; meta `11px --text-4` (DC 1430–1435) |

Rule (DESIGN-SYSTEM.md): at most two surface levels per region; no card in a card in a card.

### 2.4 Tables (RESPONSIVE.md patterns A–G) and their mobile variant

The product offer table is **pattern C (summary + details)**, built from CSS grid, not `<table>`:

```
div.cmp-t-wrap.cmp-scroll[role=table][aria-label]   overflow-x:auto; surface; r16
 └ div.cmp-t-inner                                   min-width:1180px
    ├ div.cmp-t-head[role=row]                       grid; spans role=columnheader
    └ div.cmp-t-row[role=row](.is-open)              same grid template + same min-width
       ├ cell (always)       shop block
       ├ div.cmp-t-sec       secondary cell, starts with span.cmp-t-lbl
       ├ …
       └ cell (always)       CTA + button.cmp-t-more (Details toggle, aria-expanded)
```

Grid template (head and every row, DC 951/955):
`minmax(210px,1.4fr) 132px 118px 100px 118px 118px 112px 150px`, `gap:12px`, head
`padding:12px 18px`, rows `padding:15px 18px`, row divider `1px solid var(--line-soft)`.

| Viewport | Behaviour (DC 139–151) |
| --- | --- |
| ≥721px | real table, horizontal scroll inside the card; right edge fade mask when `data-overflowing="1"` (set by `markScrollAffordance()` DC 11821, 40 ms after update / 120 ms after resize) |
| ≤720px | `.cmp-t-wrap` overflow visible; `.cmp-t-inner` min-width 0; `.cmp-t-head` hidden; `.cmp-t-row` becomes `flex column gap:10px`; `.cmp-t-lbl` shown (`700 9px/1`, `.11em`, uppercase, `--text-4`); `.cmp-t-sec` hidden until the row has `.is-open`; `.cmp-t-more` shown |

Column priority (RESPONSIVE.md): always visible = shop (rating + trust), **Total incl. shipping**,
Delivery, CTA. Behind Details = ComparoRank, Price, Shipping, Availability.

Other patterns in the app: A (admin grids, horizontal scroll with shared min-width), B (card list),
D (comparison matrix, one column per entity), E (dense admin, sticky entity column), F (timeline),
G (KPI strip, two per row). A few legacy real `<table>`s exist (e.g. DC 6853).

### 2.5 Dialogs / modals (DC 11329–11662)

Three overlay containers, mutually exclusive by `modal.type` (`buildModals()` DC 20352, 20436):

| Container | Types | Box |
| --- | --- | --- |
| Primary `modalOpen` | `auth`, `review`, `alert`, `reply`, `compliance`, `delete` | `max-width:480px` |
| Extra `modalExtraOpen` | `thread`, `deal`, `guide`, `report`, `list`, `why`, `trust`, `whyrank`, `reportoffer` | `max-width:520px` |
| List-shop `mIsListShop` | `listshop` | `width:min(720px,100%)`, top-aligned |
| Command palette `paletteOpen` | n/a | `max-width:560px`, `padding-top:12vh`, z 110 |

Scrim: `position:fixed; inset:0; z-index:100; background:var(--overlay); backdrop-filter:blur(6px);
display:flex; align-items:center; justify-content:center; padding:24px; overflow-y:auto`.
Box: `background:var(--surface); border:1px solid var(--line-2); border-radius:18px; padding:26px;
box-shadow:0 40px 90px var(--shadow); animation:cmpIn .14s ease`. Title `900 20px/1.1`, intro
`12.5px --text-4`, inputs `padding:13px 14px; r10; 1px --line-2; bg --surface-2; 13.5px`. Close is a
full-width secondary button labelled "Close"/"Cancel" with `aria-label="Close dialog"` at the bottom
(the list-shop dialog has a `×` in the header instead).

States: open/closed only; Escape closes via the global key handler (DC 11910–11911). No
loading/submitting/error state inside dialogs; validation failures show a **toast** (e.g. review
<20 chars, DC 13402). Auth-gated actions open the `auth` modal with an `after` message
(`requireAuth`, DC 13354).

### 2.6 Tabs

There is no ARIA tab pattern anywhere (`role="tab"` absent). Tabs are **pill button rows**:
`padding:9px 14px; border-radius:99px; border:1px solid var(--line-2); font:700 12.5px/1`, active
= `--acc` bg + `--acc-ink` (e.g. account tabs DC 2276–2280, `acTabs` DC 16993). Segmented
controls (auth Sign in / Create account, DC 11334–11337) use a `--surface-2` track with 4px padding
and accent-filled active segment. **Port:** use Radix Tabs (`role=tablist/tab`, `aria-selected`,
arrow keys) styled as these pills.

### 2.7 Navigation hierarchy

1. **Utility strip** (`div.cmp-utilrow`, `--surface-sub`, DC 180–223): market pickers, ⌘K, theme
   toggle, affiliate disclosure. Disclosure is `order:2`, controls `order:1`.
2. **Sticky header** (`<header>` z60, `--bg-blur` + `backdrop-filter:blur(14px)`, DC 225–372):
   wordmark, header search (all routes except home), Ask Comparo, notifications, saved, account
   menu or "Sign in".
3. **Primary nav** (`nav[aria-label="Primary"]`, DC 335–344, data `navItems()` DC 12591–12723):
   order = `Shops` (direct) · `Compare ▾` · `Deals ▾` · `Community ▾` · `Research ▾` · `List your
   shop` (accent CTA, 38px, r9). Direct/group items are 52px high, `700 15px/1`, active route =
   `inset 0 -3px 0 0 var(--acc)` underline + `--text`; open group = `--surface-3` bg and top-rounded
   `10px 10px 0 0`. A `Staff` group exists behind `if (false)` (DC 12661): dead.
4. **Mega panel** (`div.cmp-navpanel.cmp-scroll`, DC 347–370): full-width `--surface-2` band,
   `grid repeat(auto-fit,minmax(260px,1fr)) gap:28px`, columns with eyebrow titles; a highlighted
   column ("Partner with us") gets `--acc-tint` bg + `--acc-line` border. On ≤760px with the bottom
   bar visible, `boundNavPanel()` (DC 11805) turns it into a **bottom sheet** (`data-sheet="1"`,
   fixed bottom, `20px 20px 0 0` radius, drag-handle pseudo-element, max-height 72vh).
5. **Mobile bottom nav** (`nav[aria-label="Mobile"]`, z80, `display:none` until ≤760px, DC
   11664–11673): 5 equal columns Home `⌂`, Search `⌕`, Deals `%`, Community `◍`, Account `☺`
   (`mobileNavItems()` DC 12724); active = `--acc-text`, inactive `--text-3`; `min-height:44px`,
   safe-area padding.
6. **User menu** (DC 301–326, `userMenuGroups()` DC 12553): role-specific groups (member: 6 account
   links; merchant: 4 account + 5 shop-console links; admin: 4 account + 5 staff consoles), "Sign out"
   in `--danger`.
7. **Footer** (DC 11303–11327): see §4.
8. **Compare bar** (fixed bottom z70, `--bar`, `border-top:1px solid var(--acc-line)`, DC
   11270–11286): "Compare N/4", removable chips, "Compare now →" (primary), "Clear".
9. No side nav in consumer routes. Console routes (admin/intel/growth/commercial) use pill tab rows
   in-page (unverified for every console; checked for account).

### 2.8 Score presentation

| Element | Structure | Source |
| --- | --- | --- |
| **ComparoRank cell** | mono `700 17px/1` number in band colour + `700 10px` uppercase band label (`--text-4`) · 4px bar on `--chip`, width = score %, fill = band colour · tertiary button "Why this rank?" `700 10.5px` `--acc-text` | DC 973–981 |
| **Top ComparoRank tile** | same at 26px with 5px bar and "{shop} leads the organic order" | DC 1032–1037 |
| **Trust in offer row** | text only: `{stars} {rating} · trust {N}/100 · {freshLabel}`, 11px `--text-4` (no colour, no explainer on the product page) | DC 965 |
| **"Why this rank?" dialog** (`mIsWhyRank`) | title `ComparoRank {score} — {label}`; subtitle `{shop} · {total} total. Commission, subscription tier and ad spend are not inputs…`; rows grid `1fr 54px 44px`: label + 4px accent bar (pts/max), `of {max}` mono `--text-4`, `+{pts}` mono `--ok`; **Penalties** box (`--danger-tint`/`--danger-line`, rows in `--danger-2`); note that integrity rules are withheld; link "Full ranking methodology →" `#/methodology` | DC 11594–11617, data `wrData` DC 20441 |
| **"Why this?" (Best value) dialog** (`mIsWhy`) | rows `1fr auto auto`: label, weight (`max N`), value (`+pts`) in `--acc-text`. Footer copy hard-codes "Total price carries half the weight…" which matches the fallback `bestValue()` weights, **not** the ComparoRank parts actually shown when rank exists: copy inconsistency, fix in port | DC 11568–11579, 17820–17844 |
| **Trust dialog** (`mIsTrust`) | `{shop} trust score: N / 100`, rows label / weight % / value, "Full methodology →" | DC 11581–11592 |
| Coverage / price confidence | mono values coloured per §1.3 | DC 1054–1064 |

Rule (DESIGN-SYSTEM.md #5): every score shown to a user must have an explanation control next to it.
On the product page the per-row **trust** figure and the **price-confidence** tile violate this (no
explainer control): **gap**.

### 2.9 Price display

| Element | Exact treatment | Source |
| --- | --- | --- |
| Landed total (row) | label "Total incl. shipping" (mobile only via `.cmp-t-lbl`); mono `700 20px/1`, `--acc-text`, tabular-nums. Always the largest number in the row | DC 993 |
| Best total (hero) | eyebrow "Best total incl. shipping"; mono `700 34px/1` `--acc-text` | DC 848–849 |
| Effective price | mono `700 15px/1` `--text` (after coupon) | DC 984 |
| Strike-through (old price) | mono `400 11px/1`, `--text-3`, `text-decoration:line-through` + discount label `700 10px` `--danger-2` (e.g. `−12 %`). Hidden when the discount is fake or the offer anomalous (`enrichRow` DC 13151–13153) | DC 985–990 |
| Shipping | mono `600 12.5px/1.3`; `Free` in `--ok`, otherwise `--text-2`; `n/a` when not shipping | DC 992, 12967–12968 |
| Coupon chip (applied) | `margin-top:7px; padding:5px 8px; border-radius:7px; border:1px dashed var(--acc-line)`; mono `700 10.5px/1.4` `--acc-text`; text `{CODE} applied`; under it `500 9.5px/1.4` state line `{couponState} · {couponSuccess}` in the state colour (e.g. "Verified · Worked for 83 % of 24 users" / "Needs 7 more reports") | DC 1009–1012 |
| Coupon code button (deals / shop pages, copy-to-clipboard) | `padding:12px; r9; 1px dashed var(--acc-line); bg --acc-tint; --acc-text; mono 700 14px/1; letter-spacing:.06em` | DC 1538, 1800 |
| Currency formatting | `Intl.NumberFormat(locale,{style:'currency'})`, 0 decimals for CZK/SEK/PLN, else 2; EUR base × rate | seed.js 477–484 |

There is **no coupon "reveal" interaction on the product page**: the best valid coupon is
auto-applied into the total (`offerRow()` DC 12938–12951) and shown as "applied". Reveal/copy
exists only as the dashed code button on deal and shop surfaces.

### 2.10 Charts (price history, DC 1108–1162, `chart()` DC 13554–13590)

- Inline `<svg viewBox="0 0 1000 220" preserveAspectRatio="none">`, width 100%, height 220px.
- Area: `linearGradient#pgrad` `var(--acc)` at 0.28 → 0 opacity.
- Series: cheapest (or per-merchant) `polyline stroke var(--acc) 2.4`; market average `polyline
  stroke var(--text-5) 1.6 dasharray 5 5`.
- Y mapping `Y(v) = 220 − ((v−lo)/(hi−lo))·204 − 8`, `lo = min(series)·0.96`, `hi = max(avg)·1.04`.
- **Hover readout:** transparent full-size `<rect>` with `onMouseMove` / `onMouseLeave`, snapping
  to the nearest day index (`state.chartHover`). Shows a dashed vertical guide (`--text-4`, `3 4`),
  avg dot `r=3.2 --text-5`, price dot `r=4.6 --acc` with `stroke var(--bg) 2`, and two SVG `<text>`
  lines (price mono 700 16px `--text`; `{date} · avg {avg}` mono 500 12.5px `--text-4`). The label
  anchors `end` once past 72 % of the width.
- Controls: series `<select>` (Cheapest offer overall + each merchant with history) and range
  segmented buttons `7 d · 30 d · 90 d · 1 y · Max` (default 30).
- Stat strip above: Current / Period low / Period high / Change (coloured) / All-time low.
- X labels: 4 evenly spaced short dates in mono 10.5px `--text-3`. Legend swatches 14×2px.
- Gaps: mouse only (no keyboard, no touch handler), no `role="img"`/`<title>`/`<desc>`, no data
  table fallback (ACCESSIBILITY.md "Known gaps"). Because `preserveAspectRatio="none"` stretches the
  box, SVG text is horizontally distorted on wide or narrow containers (unverified visually).

### 2.11 Empty / loading / error states

| State | Pattern | Example |
| --- | --- | --- |
| Empty (inline) | surface card r14 `padding:24–26px`, `14px --text-3` sentence that names the fix | "No saved products yet. Use “Save” on any product page." DC 2283; deals DC 1523 |
| Empty with actions | title `800 16px/1.3`, explanation, row of secondary 44px buttons | product "No offers match these filters" DC 926–936 |
| Empty (popover) | centred `13px --text-3` | notifications DC 293–295, palette DC 11655–11657, picker DC 210–212 |
| Insufficient data | sentence instead of a number | `reviewSummary()` "<3 reviews: Not enough published reviews yet…" DC 17808; dashes `—` for missing values |
| Loading | `.cmp-skel` shimmer blocks (only showcased on `/design-system`, DC 8807–8808). Real routes never load (seed data is synchronous); a `ready:false` render returns a stub (DC 15478) | port must add real skeletons for Inertia partial reloads / deferred props |
| Error / feedback | **toast** only: fixed, centred, `bottom:20px` (lifted on mobile), `background:var(--text); color:var(--bg)` (inverted), `700 13px/1.3`, r10, `max-width:min(560px,90vw)`, `role="status" aria-live="polite"`, auto-dismiss 3.4 s (`toastMsg` DC 11938). The four "levels" on `/design-system` differ only in their demo swatch colour; the rendered toast is **not** colour-coded | DC 11288–11290, 13719–13724 |
| Recovered state | toast "Saved prototype state was unreadable…" | DC 11880 |
| Cross-tab | toast "Updated in another tab — reload…" | DC 11889 |

---

## 3. Product page anatomy (`#/products/{slug}`)

Routing: `parseHash()` (DC 11929) → `route.name === 'products' && route.a` → `renderVals()` calls
`buildProduct(r.a)` (DC 15582) and `productLabels(r.a)` (DC 15663); `isProduct` gates the template
(DC 788–1440). Unknown slug falls back to `S.products[0]` (DC 15844): **no 404** (port should 404).
Merged slugs resolve to the canonical product (DC 15845–15846) and show an info banner.

### 3.1 Sections top to bottom

| # | Section (DC lines) | Data shown (binding → builder) | Interactions / states |
| --- | --- | --- | --- |
| 0 | **Breadcrumb** (790–795) | Home / `pd.cat` (→ `#/search?q=cat`) / `pd.brand` (→ `#/brands/{slug}`) / `pd.name` | plain links, `12px --text-4`; not a `<nav>`/`<ol>`: **gap** |
| 1 | **Merged notice** (797–799) | `pdMergedFrom` | info banner, only when `pdMerged` |
| 2 | **Compliance banner** (800–809) | `pdCompliance.{label,reason,source,reviewed,color,bg,border}` (DC 15917; `comp()` DC 12877) | shown when status ≠ `allowed`; dot + coloured title ("Restricted in Germany" etc.) + reason + mono "source: … · reviewed by: …" |
| 3 | **Hero** (811–884), grid `repeat(auto-fit,minmax(240px,1fr)) gap:30px` | Left: 330px image placeholder + 4 thumbnails (`front, label, lifestyle, scoop`). Right: brand eyebrow; **h1** name; earned **label chips** (`pdLabels`); short description; rating strip (rating mono 18px, stars, review count · "N shops deliver to {country}" · "N % would recommend"); **price summary card**: Best total incl. shipping (34px), Price range, Per serving, Per gram of active (only `pdHasActive`); **action row**; **spec grid** (`pdSpecs`: Pack size, Servings, RRP, EAN/GTIN, Internal SKU, Category, Offers updated, Watching) | Actions: **Set price alert** (primary → auth gate → `alert` modal prefilled with 90 % of best total), **⌾ Watch / ⌾ Watching**, **☆ Save / ★ Saved**, **+ Compare / ✓ In comparison** (max 4, toast when full), **+ Add to basket compare / ✓ In basket compare**. Save/Watch/Alert require auth (`requireAuth`); Compare and Basket do not |
| 4 | **Where to buy** (887–1021) | h2 + "{shops} of {allShops} shops deliver to {country}. Totals include shipping and the best applicable coupon." | see 3.2 |
| 5 | **Buying intelligence** (1023–1066) | 6 tiles from `pdIntel` (DC 16051): Top ComparoRank, Price quality (`priceBadge`), Deal timing, Trend indicator (MA 7/30/90), Stock & price confidence, Data coverage | header "Why this rank?" opens the top eligible row's whyrank dialog, or toasts "No ranked offer for this market." |
| 6 | **Related products** (1068–1087) | up to 4 `pdRecs` from `ix.related(p)` with reason text | "Why am I seeing this?" → **toast** with reasons + score (not a dialog) |
| 7 | **Price intelligence** (1089–1106) | badge pill (`pdPrice.badge`), 8 stats (Current lowest, Current average, Median, 30-day low, 90-day low, All-time low, vs 90-day avg, Volatility), summary + explanation paragraphs (`priceStats()` DC 11954) | static |
| 8 | **Price history + Price alert** (1108–1176), grid `repeat(auto-fit,minmax(240px,2fr))` | chart card (§2.10) + alert card: suggested target (90 % of best total), "Hit N times in the last year" | Create alert (primary, auth-gated), Watch availability (secondary) |
| 9 | **About this product** (1178–1286) | description; **dose table** (`pdDose` rows: ingredient link, role label in role colour, share/NRV, amount mono, bar) + meta strip (Actives per serving, Declared share of scoop, Actives in the pack) + notes + source line; or, if no dose data, ingredient chips + "We hold no amounts…" note; **dose-limit warnings** per market (`pdLimits`); **Cheapest gram of {ingredient}** ranking (top 3 + this product highlighted `--acc-tint`); **Variants & packs** chips. Right column: **SEO & structured data** panel (canonical, hreflang, breadcrumb, JSON-LD `<pre>`) | the SEO panel is a prototype/debug artefact rendered to every visitor: **drop in production** (emit real JSON-LD in `<head>` instead) |
| 10 | **Reviews** (1288–1403) | sort select (Most helpful / Newest / Highest / Lowest rated), **Write a review** (primary, auth-gated → `review` modal with purchase-proof routes); pending-moderation warn banner (`pdPending`); **Review summary** card (verified count, photo count, generated summary); **Rating by category** bars; **What reviewers talk about** (3-segment sentiment bars); distribution card (big rating, 5→1 bars, moderation note); review cards (avatar, user, date · merchant, stars, Verified purchase badge, title, text, Pros `--ok` / Cons `--danger-2`, recommend label, Helpful N / Not helpful N / Report) | votes and report require auth; no pagination (all reviews render); empty list shows nothing but the summary "Not enough…": **gap** |
| 11 | **Community on this product** (1405–1424) | up to 4 thread rows (kind label, title, nick · cat · ago, replies, views) | "Ask the community" (auth-gated → `thread` modal prefilled "Question about {name}: "); no empty state when 0 threads: **gap** |
| 12 | **Similar products** (1426–1439) | up to 4 product cards, same category | links |

Not rendered although computed in `buildProduct`: `pdHasReviews`, `pdHasThreads`,
`pdFollowBrand` / `pdBrandFollowLabel` (follow brand), `pdLabelMissing`, `pd.updated`, `pd.rrp`.
Port decision needed (unverified whether intentionally dropped).

### 3.2 "Where to buy" interaction model (DC 887–1021, 15859–16034, 13284–13317)

Pipeline: `allOffers()` for product → `offerRow()` per offer (memoised per offer, country and
currency; DC 12928) → keep `ships` (merchant has a zone for the country) → if blocked `[]`, else
`filterRows()` → `sortRows(state.sort)`. Anomalous rows always sort last (`flaggedLast`).

| Control | Values | State key |
| --- | --- | --- |
| Warehouse select | "Any warehouse" + "Ships from {country}" per distinct warehouse | `fWarehouse` |
| Sort select | `rank` ComparoRank (organic) **default** · `recommended` · `total` Lowest total · `price` · `rating` Best rated shop · `delivery` Fastest · `discount` Biggest discount · `popular` Most popular | `sort` |
| Filter pills (with live counts) | In stock only · Free shipping · Verified shops · On discount · Has coupon · Rating 4.5+ | `fAvail fFree fVerified fDeal fCoupon fRating` |
| Result line | `aria-live="polite"`: "{n} offers from {m} shops" + removable active-filter chips + "Clear all" | derived |
| Best value strip | `pdBest` from `bestValue(rows)` (eligible rows first): "Best value" badge, "{shop} — {total} total", "score N / 100", "Why this?" → `why` dialog | |
| Anomaly notice | warn strip "Price under review" + "{n} offer(s) are held from Best value…" | `pdHasAnomaly` |
| Sponsored note | "Sponsored offers are labelled and ranked by the same organic ComparoRank…" | `pdHasSponsored` |
| Offer row | Shop block (initials tile 38×38 `--chip` r9, name link → `#/shops/{slug}`, Verified / Partner / Sponsored badges, meta line, anomaly / stale / reference-price badges) · ComparoRank · Price (+ old price, discount) · Shipping · **Total** · Availability (dot + label + "confidence {High/Good/Moderate/Unknown}") · Delivery ("{a}–{b} days", "from {warehouse}") · CTA column: **Go to shop →** (primary, `href #/go/{merchant}/{product}` = affiliate interstitial `buildGo()` DC 17026 with 3 s countdown), coupon chip, **Details** (`.cmp-t-more`, ≤720px), **Report this offer** (auth-gated → `reportoffer` dialog: Price is correct / Price has changed / Out of stock / Coupon did not work; moves price confidence, DC 13256) | `openRow` (one open row at a time) |

Filters and sort are **global component state**, not per product and not in the URL: they persist
across product pages within the session and are not shareable. **Port:** put `sort` and filters in
the Inertia query string (`?sort=total&free=1`).

### 3.3 Empty and compliance states (verified against code; discrepancies flagged)

| Condition | What the prototype renders | Spec (COMPLIANCE.md) | Port action |
| --- | --- | --- | --- |
| `allowed` | no banner | normal | same |
| `restricted` | warn banner "Restricted in {country}"; offers shown | listed with warning, CTA yes, not recommendable | same; also suppress from Related/Similar/alerts (not done in prototype, unverified) |
| `prescription_only` | info banner "Prescription only in {country}"; `rows=[]` → the generic "**No offers match these filters**" card with filter-reset fixes | offers hidden, reason shown, no CTA | show a dedicated "Not purchasable in {country}" state instead of the filter-empty card; hide filter pills and sort |
| `not_allowed` | danger banner "Not available in {country}"; same as above | same | same |
| `unknown` | warn banner "Compliance review pending"; **offers still shown** (rank −6 "Market status not verified", excluded from Best value) | **offers = [], purchasable = false** | follow the spec: block like `not_allowed` (the server must return no offers) |
| Blocked, but hero | Hero still prints Best total, price range, per-serving, and the Set price alert / Create alert CTAs, because they are computed from `pubRows` of all shipping offers, not from the blocked `rows` (DC 15862–15896) | purchase CTA "no" | hide price summary and alert CTAs when blocked (prototype bug) |
| No shop ships to country (`shipsHere=[]`) | "No offers match these filters", with "{hidden} shops do not deliver to {country}" and fix buttons incl. "Broaden market to Germany" | n/a | dedicated "No shop delivers to {country} yet" state + market switch |
| Filters exclude all | same card with Reset all filters / Remove rating threshold / Any warehouse / Broaden market to Germany (44px secondary buttons) | | keep |
| No reviews | rating shows `—`, summary sentence; review list empty with no message | | add an empty state |
| No dose data | ingredient chips + explanatory note | | keep |
| Unknown slug | silently renders product #1 | | 404 |

### 3.4 Role-specific behaviour on the product page

- **Anonymous:** everything visible; Save, Watch, Set/Create alert, Write a review, Helpful/Not
  helpful, Report review, Report offer and Ask the community open the `auth` dialog with a reason
  message (`requireAuth`). Compare and Basket work anonymously.
- **Member / merchant / admin:** identical page. No role-conditional markup exists in DC 788–1440
  (verified: no `sc-if` on session or role in that range). Staff compliance editing (`compliance`
  modal) and merchant review replies live in the admin/merchant consoles, not on this page.
- The header user menu differs by role (§2.7).

### 3.5 Market / country switcher effect on the product page

Changing the country picker sets `country` + that country's default `currency` (DC 12522), then:
shipping zones, delivery days, coupons (per-country), compliance status, totals, ComparoRank market
minimum and median (`marketStats` cache per product+country, DC 13102), dose-limit warnings, and all
"{countryName}" copy recompute. Currency alone re-formats values only. A toast confirms the change.

### 3.6 Mobile behaviour (product page)

- Hero grid collapses to one column (auto-fit 240px; ≤430px forced `1fr`).
- Offer table becomes stacked cards; secondary fields behind **Details**; totals, delivery and CTA
  always visible; `Report this offer` stays.
- Header search stays (non-home route); primary nav becomes a horizontally scrolling row with an edge
  fade; bottom nav appears (Search is active on product routes); toast and compare bar sit above it
  (the compare bar has no mobile offset and overlaps the bottom nav, both z70 vs z80: unverified
  visually, likely overlap).
- Buying-intelligence tiles 215px min → 1–2 columns; spec grid 130px min.
- Chart keeps 220px height; hover readout needs a mouse (no touch): **gap**.

---

## 4. Layout shell

| Region | Contents (exact) | Source |
| --- | --- | --- |
| Page root | `min-height:100vh; background:var(--bg); padding-bottom:env(safe-area-inset-bottom)` | DC 178 |
| Utility strip | `--surface-sub`, bottom `1px --line-soft`, inner `max-width:1440px; padding:8px 24px`. Left (order 2): accent 6px dot + "Some links are affiliate links. If you buy through them we may earn a commission — your price does not change." (11.5px `--text-3`; must never truncate, wraps on mobile). Right (order 1): picker group in a `--surface-3` r11 track with 3px padding: **Country** (flag emoji + name, searchable list "Search markets…", meta `ISO · CUR`, 300px), **Currency** (symbol + code, 230px; EUR, USD, GBP, CZK, PLN, SEK and possibly more, unverified beyond seed.js 18–23), **Language** (flag + EN/DE/FR/CS, 230px); then ⌘K button (mono) and theme toggle | DC 180–223, `topPickers()` DC 12497 |
| Picker behaviour | one open at a time (`state.picker`), `aria-expanded`, caret ▾/▴; menu `position:absolute; top:calc(100% + 7px); right:0; z-index:75; r13; shadow 0 22px 54px`; list max-height 290px; active item `--acc-tint`; rows min-height 40px; Escape closes; selecting **country also sets currency** and toasts "Delivering to {name} — totals, shipping and compliance now follow that market."; language toasts that data stays tied to delivery country. No outside-click close (unverified; no document click handler found) | DC 12508–12546, 11911 |
| Header | sticky z60, `--bg-blur` + blur(14px), bottom `1px --line`; row `padding:10px 24px; gap:16px`: wordmark → `#/`; search input (not on home) with suggestion dropdown (34px striped thumb, name, meta, price in `--acc-text`, "See all results →") or recent-searches dropdown ("Clear"); right cluster: **Ask Comparo** (accent-soft), notifications (badge count on `--acc`, popover 380px "Mark all read", empty copy), **Saved** ★ → `#/saved`, account pill (initials avatar on `--acc`, name, caret) or **Sign in** (primary) | DC 225–331 |
| Primary nav row | see §2.7 | DC 333–371 |
| Main | `max-width:1440px; margin:0 auto; padding:0 24px 140px` | DC 374 |
| Footer | `border-top:1px solid var(--line)`, `--surface-sub`, `padding:34px 0 60px`; grid `repeat(auto-fit,minmax(200px,1fr)) gap:26px`: brand block ("Independent comparison platform for legal sports nutrition. Prototype with fictional shops, brands and products.") + 6 link columns **Compare** (All products, Deal hub, Shops, Brands, Delivery markets) · **For shops** (Create a shop profile, How to get noticed, Visibility rate card, Pricing & plans, Partner network) · **Community** (Community hub, Live rooms, Groups, Demand signals, Contributions & rewards) · **Trust** (Trust centre, Labels explained, Community juries, Product wiki, Returns index, Delivery guarantee) · **Discovery** (Research & data, Ingredients, Ask Comparo, Developers & API, Newsletter) · **Platform** (Admin console, SEO & discovery, Compliance policy, Research, Your account); legal bar "© 2026 Comparo Performance — prototype. Food supplements do not replace a varied diet. Not intended for persons under 18." + mono SEO line (prototype debug: drop) | DC 11303–11327, `footerCols` DC 15569–15576 |
| Overlays | compare bar (z70), toast (z90), consent banner (z95, bottom-left 380px: "Accept all" primary / "Essential only" secondary; no reject-all wording), modals (z100), palette (z110), mobile nav (z80) | DC 11270–11673 |
| Keyboard | ⌘/Ctrl-K toggles palette; `/` opens palette; `g` then `d/f/s/c/h/r` jumps (deals, forum, saved, community, home, reviews) within 900 ms; Escape closes menus first, then palette/notifications/modal | DC 11906–11919 |
| Theme toggle | §1.1 | DC 220 |
| Head management | `applyHead()` writes title/meta/JSON-LD per route (DC 12338); port moves this to Inertia `<Head>` + server-rendered JSON-LD | |

Note: `screenshots/home-sections.png` shows an **older** header (native selects `DE · Germany`,
`EUR`, `EN` in the header row, `/en /de /fr /cs` locale switch in the strip). The current code uses
the utility-row pickers seen in `screenshots/02-chk-live.png`. Treat the code as authoritative.

---

## 5. Accessibility gaps and responsive rules for the port

### 5.1 Accessibility gaps (verified in code unless marked)

| # | Gap | Where | Port fix |
| --- | --- | --- | --- |
| 1 | **No focus trap**, no initial focus (except palette `autoFocus`), no focus return on close | all dialogs DC 11329–11662; documented in ACCESSIBILITY.md | Radix Dialog (trap, restore, `aria-labelledby`) |
| 2 | Dialogs lack an accessible name (`role="dialog" aria-modal="true"` without label, except list-shop) | DC 11330, 11501 | `DialogTitle` |
| 3 | Command palette overlay has **no `role="dialog"`**; results are not a listbox; no arrow-key navigation (unverified) | DC 11645 | Radix Dialog + `cmdk`-style combobox |
| 4 | Popovers (pickers, notifications, user menu, mega panel) have `aria-expanded` but no `aria-controls`, no menu roles, no roving focus, no outside-click close | DC 190, 275, 303, 341 | Radix Popover / DropdownMenu / NavigationMenu |
| 5 | Tabs are unlabelled pill buttons with no selected state for assistive tech | account, consoles | Radix Tabs |
| 6 | Chart range buttons, alert type chips, priority/digest segments: no `aria-pressed` | DC 1122, 11415, 11423 | `aria-pressed` / ToggleGroup |
| 7 | Toggle switches without `role="switch"`/`aria-checked`; some without labels (DC 2427) | DC 2427, 3360, 7332 | Radix Switch |
| 8 | Product-page selects (warehouse, sort, chart series, review sort) have no label | DC 894, 897, 1117, 1292 | visible or `sr-only` `<label>` |
| 9 | Hero search input (home) has only a placeholder; modal inputs rely on placeholders | DC 389, 11343–11382 | labels |
| 10 | Price chart: mouse-only hover, no keyboard/touch, no text alternative or data table | DC 1134–1152 | `role="img"` + `aria-label` summary, focusable points or a `<table>` fallback in a disclosure |
| 11 | Grid table: cells lack `role="cell"`; the Actions header is hidden with an inline clip style instead of a `sr-only` class | DC 952–1016 | `role="cell"` on cells; `sr-only` |
| 12 | Breadcrumb is not `<nav aria-label="Breadcrumb">` + `<ol>`; last item lacks `aria-current="page"` | DC 790 | fix |
| 13 | Active nav items marked only by colour and underline; no `aria-current` | DC 338–341, 11667 | `aria-current="page"` |
| 14 | Compare-bar chip remove `×` has no label; review star buttons have no label ("1 of 5 stars") | DC 11278, 11375 | `aria-label` |
| 15 | Toast severity is not conveyed (single inverted style); errors use `role="status"` | DC 11289 | variant + `role="alert"` for errors |
| 16 | Consent banner is not a dialog/region and offers no "reject" of the same prominence (unverified legal need) | DC 11292 | region with heading; equal-weight buttons |
| 17 | Icon glyphs (`◔ ★ ⌂ ⌕ % ◍ ☺`, flags) are text characters; mobile nav glyphs are not `aria-hidden` | DC 11668 | SVG icons (lucide) with `aria-hidden` |
| 18 | Chart `<text>` inside `preserveAspectRatio="none"` distorts | DC 1148 | render readout as HTML over the SVG |
| 19 | JetBrains Mono 600 used but not loaded | §1.4 | load 600 or map to 500/700 |
| 20 | Stars as text characters `★★★★☆` without an accessible rating | DC 836, 965 | `aria-label="Rated 4.3 out of 5"` |

Present and to be preserved: global `:focus-visible` ring, `aria-live="polite"` result count,
`aria-pressed` filter pills, `aria-expanded` Details toggle, labelled icon buttons in the header,
44px targets via `--tap`, reduced motion, landmarks (`header`, `nav[aria-label]`, `main`, `footer`).

### 5.2 Responsive rules to carry over

1. Breakpoints: 430 / 720-721 / 760 / 900 px (+ `hover:none`) (§1.5).
2. Consumer lists never scroll horizontally: offers, shops and deals collapse to cards at ≤720px
   (`.cmp-t-*` contract). Admin grids (pattern A/E) scroll inside their card with a shared
   `min-width` on header and rows, plus the edge-fade affordance (`data-overflowing`).
3. Card grids use `repeat(auto-fit|auto-fill, minmax(N,1fr))`; below 430px they are forced to one
   column. In Tailwind, express as `grid-cols-1 xs:grid-cols-[repeat(auto-fit,minmax(240px,1fr))]`,
   not via the prototype's attribute-selector hack (`[style*="repeat(auto-fit"]`).
4. Global `min-width:0` on text/flex children and `overflow-wrap:break-word` prevent overflow; keep
   them in base CSS.
5. ≤760px: bottom nav (72px body padding), scrolling primary nav row with edge mask, mega panel as a
   bottom sheet sized to 72vh, toasts lifted above the bar.
6. Legal/affiliate disclosure and compliance text never truncate.
7. Verification sweep in RESPONSIVE.md (no grid overflows without a scroller;
   `scrollWidth === clientWidth`) at 320, 360, 390, 430, 480, 640, 768, 1024, 1280, 1440 and 1920 px,
   both themes: port it to a Playwright/Pest browser test.

---

## 6. Recommended React component breakdown

Conventions: TypeScript, one component per file, props are plain serialisable data from Inertia
(the server computes every score, total and label, per PERFORMANCE.md "still to do"). Money arrives
pre-formatted **and** as minor units (`{ amount: number; formatted: string }`). Tokens only; no raw
hex in components. shadcn primitives are wrapped once in `resources/js/components/ui/*` and restyled.

### 6.1 Shared types (`resources/js/types/catalog.ts`)

```ts
type Tone = 'ok' | 'warn' | 'danger' | 'info' | 'acc' | 'neutral' | 'muted';
type Money = { amount: number; currency: string; formatted: string };
type ComplianceStatus = 'allowed' | 'restricted' | 'prescription_only' | 'not_allowed' | 'unknown';
type RankBand = 'Exceptional' | 'Excellent' | 'Good' | 'Fair' | 'Low confidence';
interface RankBreakdown { score: number; band: RankBand; tone: Tone;
  parts: { key: string; label: string; points: number; max: number }[];
  penalties: { label: string; points: number }[]; hiddenPenaltyCount: number; eligibleBestBuy: boolean }
interface OfferRow { id: number; merchant: { name: string; slug: string; initials: string; rating: number;
  trustScore: number; verified: boolean; partner: boolean }; sponsored: boolean;
  price: Money; oldPrice: Money | null; discountLabel: string | null; shipping: Money | null /* null = n/a */;
  total: Money; availability: { label: string; tone: Tone; confidence: 'High' | 'Good' | 'Moderate' | 'Unknown' };
  delivery: { minDays: number; maxDays: number; warehouseName: string } | null;
  rank: RankBreakdown; coupon: { code: string; stateLabel: string; tone: Tone; successText: string } | null;
  flags: { anomaly: boolean; stale: boolean; referencePriceUnverified: boolean }; freshLabel: string; goUrl: string }
```

### 6.2 Layout shell

| Component | Path | Props |
| --- | --- | --- |
| `AppLayout` | `resources/js/layouts/AppLayout.tsx` | `{ children; market: MarketContext; auth: SessionUser \| null; nav: NavGroup[]; footer: FooterColumn[]; flash?: Toast }` |
| `UtilityBar` | `components/navigation/UtilityBar.tsx` | `{ disclosure: string; market: MarketContext; onOpenPalette(); }` |
| `MarketPicker` | `components/navigation/MarketPicker.tsx` | `{ kind: 'country' \| 'currency' \| 'language'; value: string; options: { value; label; meta?; glyph? }[]; searchable?: boolean; onSelect(v) }` (Radix Popover; country change posts to a `market.update` route and reloads Inertia props) |
| `ThemeToggle` | `components/navigation/ThemeToggle.tsx` | `{ theme: 'light' \| 'dark' \| 'system' }` (writes `data-theme` + cookie; optional system option) |
| `SiteHeader` | `components/navigation/SiteHeader.tsx` | `{ showSearch: boolean; auth; unreadCount: number; savedHref: string }` |
| `HeaderSearch` | `components/search/HeaderSearch.tsx` | `{ initialQuery?: string; size: 'header' \| 'hero' }` (combobox, suggestions via a JSON endpoint, recent searches) |
| `PrimaryNav` / `MegaPanel` | `components/navigation/PrimaryNav.tsx`, `MegaPanel.tsx` | `{ items: NavItem[]; activeKey: string }` / `{ group: NavGroup; asSheet: boolean }` (Radix NavigationMenu; Sheet on mobile) |
| `NotificationsPopover` | `components/navigation/NotificationsPopover.tsx` | `{ items: Notification[]; unread: number }` |
| `UserMenu` | `components/navigation/UserMenu.tsx` | `{ user: SessionUser; groups: MenuGroup[] }` |
| `MobileTabBar` | `components/navigation/MobileTabBar.tsx` | `{ items: { label; href; icon; active }[] }` |
| `SiteFooter` | `components/navigation/SiteFooter.tsx` | `{ columns: FooterColumn[]; legal: string }` |
| `CompareTray` | `components/compare/CompareTray.tsx` | `{ items: { id; name }[]; max: 4 }` |
| `Toaster` | `components/feedback/Toaster.tsx` | driven by flash + client events; variants `success/info/warn/error` |
| `ConsentBanner` | `components/feedback/ConsentBanner.tsx` | `{ onAccept(level: 'all' \| 'essential') }` |
| `CommandPalette` | `components/navigation/CommandPalette.tsx` | `{ open; onOpenChange }` (Radix Dialog + list, ⌘K `/` `g`-jumps) |

### 6.3 UI primitives (`components/ui`, Comparo-styled wrappers)

`Button` (`variant: primary | secondary | tertiary | destructive | icon | accentSoft`,
`size: sm | md | lg`), `Pill` / `FilterChip` (`pressed`, `count`), `RemovableChip`, `Badge`
(`kind: verified | verifiedPurchase | partner | exclusive | bestValue | sponsored | officialResponse |
underReview | referenceUnverified | marketUnverified | stale | invalid | freeShipping`), `LabelChip`
(`name; tone; why; notClaim`), `Card` (`level: 1 | 2`, `radius: lg | xl`), `Eyebrow`, `Stat`
(`label; value; tone?; mono?; size`), `ScoreBar` (`value; tone; height: 3 | 4 | 5 | 6`), `Notice`
(`tone; title?; children`), `Placeholder` (`label; height`), `Skeleton`, `EmptyState`
(`title?; body; actions?`), `Dialog`, `Select` (always labelled), `Switch`, `Tabs`,
`SegmentedControl`, `Money` (renders mono tabular value), `Stars` (with accessible label).

### 6.4 Product page (`resources/js/pages/Products/Show.tsx`)

Page props (from `ProductController@show`): `{ product: ProductDetail; market: MarketContext;
compliance: ComplianceNotice | null; offers: { rows: OfferRow[]; total: number; hiddenCount: number;
filteredCount: number; best: BestValue | null; anomalyNote?: string; sponsoredNote?: string };
query: { sort: SortKey; filters: OfferFilters; warehouse?: string }; facets: OfferFacetCounts;
warehouses: Option[]; intelligence: BuyingIntel; priceStats: PriceStats; history: PriceHistory
(deferred prop); alertSuggestion: Money & { hitCount: number }; dose: DosePanel | null; doseLimits:
DoseLimit[]; cheapestPerGram: PerGramRanking | null; reviews: ReviewsBlock (deferred / paginated);
threads: ThreadRow[]; related: RelatedProduct[]; similar: ProductCard[]; viewer: { saved; watching;
inCompare; inBasket; canInteract: boolean } }`.

| Component | Path | Props |
| --- | --- | --- |
| `Breadcrumbs` | `components/navigation/Breadcrumbs.tsx` | `{ items: { label; href? }[] }` |
| `MergedNotice` | `components/catalog/MergedNotice.tsx` | `{ fromName: string }` |
| `ComplianceBanner` | `components/compliance/ComplianceBanner.tsx` | `{ status: ComplianceStatus; countryName; reason; source; reviewedBy }` |
| `ProductHero` | `components/catalog/ProductHero.tsx` | `{ product; labels: LabelChipProps[]; ratingSummary; shopsDelivering: number; countryName; children }` |
| `ProductGallery` | `components/catalog/ProductGallery.tsx` | `{ images: { src?; alt; label }[] }` |
| `PriceSummaryCard` | `components/offers/PriceSummaryCard.tsx` | `{ bestTotal: Money \| null; priceRange; perServing; perActiveGram?; purchasable: boolean }` + `ProductActions` |
| `ProductActions` | `components/catalog/ProductActions.tsx` | `{ productId; saved; watching; inCompare; inBasket; canAlert: boolean }` (auth-gated actions open `AuthDialog` with a reason) |
| `SpecGrid` | `components/catalog/SpecGrid.tsx` | `{ specs: { label; value }[] }` |
| `WhereToBuy` | `components/offers/WhereToBuy.tsx` | `{ offers; query; facets; warehouses; countryName; blocked: boolean }` |
| `OfferToolbar` | `components/offers/OfferToolbar.tsx` | `{ sort; warehouse; sortOptions; warehouseOptions; onChange }` (URL-synced via `router.get(..., { preserveScroll, only: ['offers'] })`) |
| `OfferFilterBar` | `components/offers/OfferFilterBar.tsx` | `{ filters; counts; resultLabel; onToggle(key); onClear() }` |
| `BestValueStrip` | `components/ranking/BestValueStrip.tsx` | `{ shop; total: Money; score: number; breakdown: RankBreakdown }` |
| `OfferTable` | `components/offers/OfferTable.tsx` | `{ rows: OfferRow[]; caption: string }` (renders the `.cmp-t-*` contract with Tailwind; `role=table/row/columnheader/cell`) |
| `OfferRow` | `components/offers/OfferRow.tsx` | `{ row: OfferRow; expanded: boolean; onToggleDetails() }` |
| `MerchantCell` | `components/offers/MerchantCell.tsx` | `{ merchant; sponsored; flags; freshLabel }` |
| `PriceCell` / `TotalCell` / `ShippingCell` | `components/offers/*` | `{ price; oldPrice; discountLabel }` / `{ total }` / `{ shipping }` |
| `AvailabilityCell` / `DeliveryCell` | `components/offers/*` | `{ availability }` / `{ delivery }` |
| `CouponAppliedChip` | `components/offers/CouponAppliedChip.tsx` | `{ code; stateLabel; tone; successText }` |
| `CouponCodeButton` | `components/offers/CouponCodeButton.tsx` | `{ code; onCopied? }` (deals/shops) |
| `GoToShopButton` | `components/offers/GoToShopButton.tsx` | `{ href: string; merchantName: string }` (outbound `rel="sponsored nofollow"`) |
| `ReportOfferDialog` | `components/offers/ReportOfferDialog.tsx` | `{ offerId; merchantName; open; onOpenChange }` |
| `OffersEmptyState` | `components/offers/OffersEmptyState.tsx` | `{ reason: 'filters' \| 'no-delivery' \| 'blocked'; countryName; hiddenCount; filteredCount; fixes }` |
| `ComparoRankCell` | `components/ranking/ComparoRankCell.tsx` | `{ rank: RankBreakdown; onExplain() }` |
| `RankExplainerDialog` | `components/ranking/RankExplainerDialog.tsx` | `{ merchantName; total: Money; rank: RankBreakdown; open; onOpenChange }` |
| `TrustScore` / `TrustExplainerDialog` | `components/ranking/*` | `{ score; label; tone; signals }` |
| `BuyingIntelligence` | `components/ranking/BuyingIntelligence.tsx` | `{ intel: BuyingIntel; onExplainTop() }` with `IntelTile` `{ eyebrow; value; tone; detail; bar? }` |
| `RelatedProducts` | `components/catalog/RelatedProducts.tsx` | `{ items: { card: ProductCard; reason: string; reasons: string[]; score: number }[] }` (use a Popover for "Why am I seeing this?") |
| `PriceIntelligence` | `components/pricing/PriceIntelligence.tsx` | `{ stats: PriceStats }` |
| `PriceHistoryChart` | `components/pricing/PriceHistoryChart.tsx` | `{ series: { date: string; min: number; avg: number }[]; range: 7 \| 30 \| 90 \| 365 \| 0; merchantOptions; merchantId; currency; onRangeChange; onSeriesChange }` (HTML readout overlay, keyboard stepping, `<table>` fallback) |
| `PriceAlertCard` / `PriceAlertDialog` | `components/alerts/*` | `{ suggestion: Money; hitCount; countryName; watching }` / `{ productId; defaultTarget; currency; open }` |
| `DosePanel` | `components/nutrition/DosePanel.tsx` | `{ rows: DoseRow[]; meta: DoseMeta } \| { ingredients: IngredientChip[] }` |
| `DoseLimitNotice` | `components/compliance/DoseLimitNotice.tsx` | `{ limits: DoseLimit[] }` |
| `CheapestPerGram` | `components/nutrition/CheapestPerGram.tsx` | `{ ingredient; verdict; rows: { rank; name; href; per; detail; isCurrent }[] }` |
| `VariantChips` | `components/catalog/VariantChips.tsx` | `{ variants: string[]; packs: string[] }` |
| `ReviewsSection` | `components/reviews/ReviewsSection.tsx` | `{ block: ReviewsBlock; sort; canWrite; pendingOwn: boolean }` |
| `ReviewSummary` / `RatingDistribution` / `SubRatingBars` / `TopicSentiment` | `components/reviews/*` | `{ text; verifiedCount; photoCount }` / `{ average; count; buckets }` / `{ items }` / `{ topics: { topic; label; tone; count; pos; mixed; neg }[] }` |
| `ReviewCard` | `components/reviews/ReviewCard.tsx` | `{ review: ReviewItem; onVote(dir); onReport() }` |
| `WriteReviewDialog` | `components/reviews/WriteReviewDialog.tsx` | `{ kind: 'product'; targetId; targetName; proofRoutes }` |
| `CommunityThreads` | `components/community/CommunityThreads.tsx` | `{ threads: ThreadRow[]; onAsk() }` |
| `SimilarProducts` / `ProductCard` | `components/catalog/*` | `{ items: ProductCard[] }` / `{ product: ProductCard; compact? }` |
| `AuthDialog` | `components/auth/AuthDialog.tsx` | `{ reason?: string; tab: 'login' \| 'register' }` |

Suggested file tree:

```
resources/js/
  layouts/AppLayout.tsx
  pages/Products/Show.tsx
  components/
    ui/            Button Badge Pill Card Notice Stat ScoreBar Dialog Select Switch Tabs Skeleton EmptyState Money Stars
    navigation/    UtilityBar MarketPicker ThemeToggle SiteHeader PrimaryNav MegaPanel UserMenu NotificationsPopover MobileTabBar SiteFooter Breadcrumbs CommandPalette
    search/        HeaderSearch
    catalog/       ProductHero ProductGallery ProductActions SpecGrid VariantChips RelatedProducts SimilarProducts ProductCard MergedNotice
    offers/        WhereToBuy OfferToolbar OfferFilterBar OfferTable OfferRow MerchantCell PriceCell TotalCell ShippingCell AvailabilityCell DeliveryCell CouponAppliedChip CouponCodeButton GoToShopButton ReportOfferDialog OffersEmptyState PriceSummaryCard
    ranking/       ComparoRankCell RankExplainerDialog BestValueStrip BuyingIntelligence IntelTile TrustScore TrustExplainerDialog
    pricing/       PriceIntelligence PriceHistoryChart
    alerts/        PriceAlertCard PriceAlertDialog
    nutrition/     DosePanel CheapestPerGram
    compliance/    ComplianceBanner DoseLimitNotice
    reviews/       ReviewsSection ReviewSummary RatingDistribution SubRatingBars TopicSentiment ReviewCard WriteReviewDialog
    community/     CommunityThreads
    compare/       CompareTray
    feedback/      Toaster ConsentBanner
    auth/          AuthDialog
```

### 6.5 Port decisions this map surfaces

1. Theme: keep `data-theme` on `<html>`, persist in a cookie, render server-side; decide whether the
   accent override survives (recommended: no).
2. Compliance: follow COMPLIANCE.md (`unknown` blocks; blocked products hide price summary and alert
   CTAs); fix both prototype divergences.
3. Offer sort and filters move into the URL query; server-side filtering and sorting.
4. Replace the product-page SEO debug panel and the footer SEO line with real `<head>` output.
5. Unknown slugs 404; merged slugs 301 (as the banner copy already promises).
6. Normalise the ad-hoc px scale (gaps, font sizes 9–10.5px) to a token scale, keeping the smallest
   meta text at ≥10px on `--text-4` or better (DESIGN-SYSTEM.md).
7. Load JetBrains Mono 600 or remove 600 usages.
8. Fix the "Why this?" copy so it describes the ComparoRank parts it actually shows.
