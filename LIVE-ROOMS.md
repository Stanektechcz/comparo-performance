# Live rooms

A room for every forum section, every major market, every group and every scheduled session —
38 in total. The room is attached to the section, shares its moderators and its policy, and its
useful exchanges are **promoted into a topic** rather than left to scroll away.

## Why a comparison engine should have rooms at all

Two people decide differently. One wants the answer that is still correct next year — that is a
topic. One has a checkout open in the next tab and needs an answer in four minutes — that is a
room. The forum only ever served the first.

## What makes ours different from a chat widget

* **Price events are messages.** When an offer inside a room's scope moves, the room says so,
  with the product, the shop and the record attached. No other participant in this category can
  broadcast that, because none of them holds the offer log.
* **Promotion, not archiving.** A moderator (or any member at Curator level) selects two or more
  messages and promotes them into a topic in the same section, with attribution. Everyone quoted
  earns contribution points for it.
* **Slow mode per room, not per platform.** Deals is 10 s, Beginner Questions is 0.
* **Nothing in a room is for sale.** Rooms are on the advertising page's "not for sale at any
  price" list, beside ranking position and review weight.

## Transport (prototype vs deployment)

Messages the reader sends are written to shared browser storage and broadcast on a
`BroadcastChannel`, so a second tab or window receives them live, with presence and typing
indicators. Seeded members talk on a per-tab ambient timer; those messages are never persisted
and never broadcast, so two tabs cannot double them and a reload cannot accumulate a fake
history. A deployment replaces the transport with a socket; nothing above `live.js` changes.

Seeded history is authored relative to the catalogue's NOW and shifted onto the real clock once
at engine start — two clocks in one list would sort wrongly and print nonsense relative times.

## Files

| File | Holds |
|---|---|
| `seed-live.js` | rooms, seeded history, ambient script, groups, events, polls, bounties, experts, promotions |
| `live.js` | `ComparoLive(SEED)`: transport, presence, typing, slow mode, rate limit, moderation, promotion |
| `RoomPanel.dc.html` | the room itself — used on the live hub, forum sections, group and event pages |

## Surfaces

`#/live`, `#/live/<room>`, every `#/forum/<section>`, `#/groups/<slug>`, `#/events/<slug>`,
and the Live rooms tab of `#/community`. A topic page links to its section's room with the
current head count.
