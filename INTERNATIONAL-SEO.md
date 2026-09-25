# International SEO

## Three independent axes

| Axis | Meaning | Affects |
| --- | --- | --- |
| Language | content language (`en`, `de`, `cs`, `fr`, `es`, `it`, `pl`) | copy, meta, UI |
| Market / delivery country | where the parcel goes | which offers exist at all, shipping, compliance |
| Currency | presentation | displayed numbers only |

A German-speaking user can select Switzerland; a Czech user can shop in EUR. The three are never
collapsed into one setting, and currency never creates a URL.

## Locale routing

Production URL shape: `/{locale}{entity-path}` — e.g. `/de/products/whey-isolate-90`. The market
lives on explicit market URLs (`/countries/germany`) and in the user's delivery selection, not in
the locale prefix. The prototype runs the English locale with the locale switcher and full
hreflang matrix wired in the head.

## hreflang model

Emitted per page, including `x-default`:

```
en-DE, de-DE, de-AT, cs-CZ, fr-FR, es-ES, it-IT, pl-PL, en-GB, en-US, x-default
```

The console shows the full matrix for any inspected URL, so a missing or asymmetric pair is
visible before it ships.

## Market landing pages

A market page must not be a translated global page. `/countries/{country}` derives everything from
market data: shops that actually deliver, real shipping costs and delivery windows, the cheapest
total available there, VAT, currency, market-specific reviews and discussions, and the count of
products restricted in that market.

## Localisation management

Per-locale coverage and status (`missing`, `machine draft`, `human reviewed`, `approved`) are
tracked in the console. Machine drafts are labelled as machine drafts — never presented as
human-reviewed content.

## Compliance is per market, not per language

Market status governs purchasability regardless of the language the visitor reads. The same product
page in German shows a purchase table for Austria and a legal-basis notice for Germany when the
recorded status differs.
