# Contributions, levels and rewards

Gamification with one job: **collect verifiable information**. Every point is paid for a record a
stranger could check, and nothing is paid for presence, opinion volume or streak length alone.

## The three rules that keep it from becoming a farm

1. **Provisional for 48 hours.** A price report moderation cannot reproduce is reversed, and the
   reversal is visible on the profile. Reversal rate above 25 % suspends the data sources for 30
   days; community sources stay open.
2. **Every source has a daily cap.** The twentieth price report of a day is worth less than the
   first, and an uncapped source is an invitation.
3. **Rewards are platform capability only** — days of Plus, alert capacity, bounty stake, an
   export, a funded lab test. Never money, never products, never rank. We do not sell goods, so a
   reward shaped like a product would be a lie about what we are.

## What each source buys us

| Source | XP | Cap/day | Feeds |
|---|---|---|---|
| Dosing correction | 60 | 1 | dosing accuracy |
| Label photograph | 45 | 2 | price per gram of active |
| Delivery report | 30 | 3 | delivery medians, promise compliance |
| Price correction | 25 | 5 | offer accuracy |
| Coupon outcome | 20 | 6 | coupon validity |
| Stock correction | 15 | 5 | stock confidence |
| Verified review | 80 | 2 | product and shop ratings |
| Accepted answer | 70 | 3 | question resolution |
| Guide published | 150 | 1 | evergreen content |
| Chat promoted to a topic | 40 | 2 | archive quality |

Ten levels, each unlocking capability rather than decoration: reporting at 2, label photographs
at 4, guides and bounties at 5, group creation at 6, dosing edits at 7, trusted flagger at 8.

## Seasons

A season names one data gap and closes it. Season 3 is dosing: a photographed amount-per-serving
panel for all 46 products. The leaderboard is computed from what members actually contributed,
never typed. Past seasons keep their verdict, including Season 2, which missed.

## Files

`seed-gamify.js` (sources, levels, quests, season, rewards, integrity rules, seeded ledger) and
`gamify.js` (`ComparoGamify(SEED)`: totals, level, caps, streak, quest progress, badges,
leaderboard, rewards, earned entitlements). Surface: `#/rewards`, plus the Leaderboard tab on
`#/community`.
