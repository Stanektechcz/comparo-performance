# AI search

## Position

AI search is treated as an extension of strong SEO and clear structure — not as a separate
keyword game. The same things that make a page useful to a person make it quotable by an answer
engine: a direct answer, original data, named entities, visible provenance and a date.

## Crawler policy

| Bot | Kind | Default |
| --- | --- | --- |
| Googlebot | traditional search crawler | allow |
| Bingbot | traditional search crawler | allow (also feeds downstream AI answers) |
| OAI-SearchBot | AI **search discovery** crawler | allow |
| GPTBot | **model-training** crawler | disallow |
| PerplexityBot | AI answer engine with citations | allow |
| CCBot | bulk archive | disallow |

Search discovery and model training are different decisions and are configured separately;
toggling any bot in **SEO & Discovery → AI visibility** rewrites the generated robots.txt in the
same panel and writes an audit entry.

## Citation readiness (0–100)

Ten factors per informational page: direct answer block, original data, source transparency,
entity clarity, exposed updated date, author attribution, structured headings and tables,
machine-readable facts, canonical entity links, structured data. The console lists every page with
its score and exactly which factors are missing.

## Answer blocks

Reusable blocks used across product, category, ingredient, market, guide and research pages:
**Quick answer**, **Key facts** (machine-readable), **Comparison table**, **Price snapshot**,
**Trust snapshot**, **FAQ**, **Methodology**, **Sources**. They are human-first: each one is the
thing a visitor wants at the top of the page anyway.

## Ask Comparo

`/ask` answers questions from the dataset with no external model:

- parses the question into market, product, shop, price ceiling and rating floor;
- retrieves offers, price history, ratings, trust scores and compliance records;
- composes a sentence from the retrieved values;
- lists the **entities used** as clickable sources;
- reports **High / Medium / Low confidence** from data coverage;
- states data age when the answer depends on a price ("cheapest offer last refreshed 4 h ago").

It refuses to answer beyond the data: an unknown market status produces "market availability is
not verified" rather than a guess, and a restricted product produces the legal basis instead of a
purchase recommendation. The AI Query Lab in the admin console runs the same engine for internal
testing.

## Stale answer protection

If the cheapest offer backing an answer is older than the freshness window, the answer carries the
age explicitly. Stale values are never presented as live.

## What we refuse to do

Mass low-quality generated pages, fake authors, fabricated statistics, keyword stuffing, hidden
text, doorway pages, cloaking, machine-generated reviews, invented user experiences, fake
citations. Programmatic pages exist only where unique data exists.

## Prototype boundaries

AI citation counts are manually logged or CSV-imported and labelled as prototype data — there is
no live Bing Webmaster or ChatGPT API connection. The retrieval, answer composition, sourcing,
confidence and staleness logic are real and run on the seeded dataset.
