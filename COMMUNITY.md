# Community

The community layer turns Comparo from a price table into a place where buyers compare notes.
Four surfaces, one identity, one moderation pipeline.

| Surface | Route | What it is |
| --- | --- | --- |
| Community hub | `/community` | Activity feed + trending discussions + top contributors + unanswered questions + deals + guides |
| Forum | `/forum`, `/forum/{category}`, `/forum/topic/{slug}` | 14 moderated categories, discussions and questions |
| Guides | `/guides`, `/guides/{slug}` | Long-form member knowledge, moderated before publishing |
| Public profiles | `/users/{username}` | Reputation, badges, reviews, topics, deals |

## Content types

**Thread** — `kind: discussion | question`. A question can have exactly one accepted answer, set by
the thread author (or a moderator). Accepted answers render pinned above the reply list and award
+15 reputation to the answer's author. Threads carry optional `productId`, `merchantId` and
`country`, which is how a topic surfaces on a product page, a shop page and a country hub.

**Reply** — flat list with optional quote-of. Deliberately not deeply nested: quoting shows a
single collapsed excerpt instead of an indentation tree, so mobile stays readable.

**Guide** — title, category, tags, body paragraphs, related products and shops. Submitted guides
enter `pending`; only the author sees them until a moderator publishes.

**Community deal** — shop, product, price, original price, coupon code, country, expiry and a
description. Submitted deals enter `pending`. Once approved they appear in the deal hub next to
merchant and platform deals, labelled as community-found.

**Activity item** — the unified feed row (review, discussion, question, deal, guide). Filterable
by type; muted users disappear from it.

## Voting model

Deliberately asymmetric to avoid pile-ons:

- Reviews: **Helpful / Not helpful** (one vote per account per review).
- Threads, replies, guides, answers: **Upvote only**. No downvotes.
- Community deals: **Good deal / Expired / Incorrect** → community confidence percentage
  (`good / (good + expired + incorrect)`), shown as "92 % think this is a good deal".

## Trending score

```
engagement = views * 0.02 + votes * 3 + replies * 6
trending   = engagement / (hours_since_last_activity + 2) ^ 0.62
```

Used for Trending Discussions, Trending Reviews and Trending Deals. Time decay is sub-linear so a
genuinely useful thread stays visible for a few days instead of one afternoon.

## Follow graph

Users follow **users, products, brands, shops, forum topics and categories**. Follows drive the
personal feed and the notification centre; they are stored as a flat map (`type:id`) in the
prototype and as a polymorphic `follow` table in production.

## Tags

One global tag vocabulary shared by threads, guides, reviews and deals (`shipping`, `germany`,
`eu`, `deal`, `comparison`, `beginner`, `shop-review`, `brand`, `price-history`, `compliance`…).
A tag page is the join across all four content types — that is the point of one vocabulary.

## Country scoping

Every community item can carry a country tag, and country hubs (`/country/{iso}`) show the
threads, deals, shops and reviews for that market. Cross-border shipping questions are market
questions; treating them globally makes the answers useless.

## Mute and block

Muting hides a member from your feed, thread lists and reply lists without telling them. Blocking
additionally prevents replies to your content. Neither removes their public reviews — moderation,
not personal preference, decides what is published.

## Merchant participation

Verified merchants get one official reply per review, an official-answer badge in shop Q&A, and
labelled announcements (shipping updates, return policy, new delivery countries). Merchants
**cannot** delete reviews, change ratings, hide criticism or post generic advertising. Every
merchant action is attributable and logged.

## Prototype notes

State lives in browser storage under `comparo.proto.v2`: `threads`, `replies`, `guides`,
`userDeals`, `follow`, `votesUp`, `reputation`, `badgesEarned`, `muted`, `lists`, plus override
maps for seeded content (`ovThreads`, `ovDeals`, `ovGuides`). Seed data lives in
`seed-community.js`: 20 threads, ~110 replies, 8 guides, 15 community deals, 35 members with
reputation and badges, shop Q&A, merchant announcements and a 60-item activity feed.
