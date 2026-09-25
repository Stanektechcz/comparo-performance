# Structured data

## Generator

One reusable JSON-LD generator (`jsonLd()`) emits markup only where the page content supports it,
and writes it into a live `<script type="application/ld+json">` in the document head.

| Page | Emitted |
| --- | --- |
| Home | `WebSite` + `SearchAction`, `Organization` |
| Product | `Product` (with `productID` = entity ID, `sku`, `gtin13`, `brand`, `category`), `AggregateOffer` when offers exist, `AggregateRating` **only** when published reviews exist, `BreadcrumbList` |
| Shop | `Store` with `AggregateRating`, `BreadcrumbList` |
| Brand / Category / Ingredient / Country | `Brand` / `CollectionPage` / `DefinedTerm` + `ItemList`, `BreadcrumbList` |
| Guide / article | `Article` + `Person` (author), `BreadcrumbList` |
| Research report | `Dataset` + `Article` |
| Forum topic | `DiscussionForumPosting` |
| Comparison | `ItemList` |

Planned and modelled but not emitted until real assets exist: `VideoObject` (guide videos:
title, description, thumbnail, duration, upload date, related entities), `ImageObject` for image
sitemaps, `FAQPage` where the questions come from real platform data.

## Rules

1. No rating markup without published reviews. No fabricated review markup, ever.
2. `AggregateOffer` only from live offers, with the currency actually stored.
3Ratings and prices in markup always match what the page renders.
4. Entity IDs (`PRD-00001`, `SHP-00003`, `ING-00006`) are emitted as `productID`/`identifier`
   so the same entity is recognisable across pages, feeds and future APIs.
5. Breadcrumbs are generated from the real hierarchy, not from the URL string.

## Debugger

**SEO & Discovery → Technical** shows the JSON-LD emitted on the current route, the detected
entity types per page in the registry, and flags indexable pages with no structured data at all
(a −12 SEO deduction).

## Images

Product images carry a canonical asset URL, alt text, dimensions and an entity relation, ready for
an image sitemap and `srcset`/WebP/AVIF delivery. The prototype uses labelled striped placeholders
instead of stock photography so no misleading imagery enters the dataset.
