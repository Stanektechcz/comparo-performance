# Accessibility

Target: **WCAG 2.1 AA**.

## Landmarks and headings

`<header>`, `<nav aria-label>`, `<main>`, `<section>`, `<footer>` on every route. Exactly one `<h1>`
per page, then `<h2>` per section and `<h3>` per card — no skipped levels.

## Contrast

Text 4.5:1 against its own background; headline-scale type 3:1. Ink is full-opacity on accent and
tinted grounds — never alpha-muted body text. Both themes are audited independently, including
muted text, placeholders, badges, table headers, disabled states and chart labels.

## Keyboard

* Global focus ring: `outline: 2px solid var(--focus)` with a 2px offset, never removed.
* `⌘K` / `Ctrl+K` opens the command palette: search, navigation and role-appropriate actions.
* Search suggestions: ↑ / ↓ to move, Enter to open, Escape to dismiss.
* Modals: Escape closes, the backdrop is a `role="dialog" aria-modal="true"` container, and the
  close control is labelled.
* Every control reachable by pointer is reachable by keyboard, including the Details toggles that
  only appear on narrow screens.

## Names and roles

Icon-only controls (theme, command palette, notifications, quantity ±, remove, close) carry both an
`aria-label` and a `title`. Filter chips expose `aria-pressed`; disclosure controls expose
`aria-expanded`. Grid-based tables carry `role="table" / row / columnheader` so the visual table and
the accessibility tree agree.

## Live regions

Result counts and filter changes announce through `aria-live="polite"`. Toasts announce success and
failure. Nothing important is announced only through colour.

## Motion

`prefers-reduced-motion: reduce` collapses all animation and transition durations to ~0. No
decorative animation sits between a click and its result.

## Touch and zoom

Minimum 44×44px targets on mobile surfaces. Layout survives 200 % desktop zoom and enlarged browser
text because widths are `max-width` and tracks are `minmax()` — the design has no fixed-width
containers wider than the viewport.

## Known gaps (prototype)

Charts are decorative SVG with textual summaries beside them rather than fully described data
tables; a production build should ship `<table>` fallbacks. Focus is not yet programmatically
trapped inside modals (Escape and the labelled close control work).
