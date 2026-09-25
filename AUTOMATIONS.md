# Automations

## Admin rule engine (`/intel → Automations`)

IF trigger THEN action, evaluated against live prototype data.

| Rule | Trigger | Actions |
|---|---|---|
| Stale offer guard | offer stale > 48 h | deprioritise, notify merchant |
| Price anomaly hold | ±60 % vs median | flag, queue review, exclude from Best value |
| Review burst screen | > 8× hourly baseline | queue moderation, hold publication |
| Merchant risk escalation | risk ≥ HIGH | create task, notify |
| Deal expiry sweep | ends < 24 h | notify, mark ending soon |
| Unmatched feed digest | > 20 unmatched rows | create task, notify merchant |
| Broken affiliate link | any 404 / loop | hide offer, create task, notify merchant |
| Compliance unknown block | status = unknown | flag, suppress recommendation |

Triggers available: price anomaly, feed stale, review flagged, merchant risk increase, deal expiry,
product unmatched, broken affiliate link, compliance unknown.
Actions available: notify, flag, hide, deprioritise, create task, queue moderation.

Pausing a rule stops its effects immediately — the automation log and the surfaces it feeds both
update. Every pause, resume and manual run is audit-logged.

## Automation log

Rule, entity, action, outcome, timestamp. Entries are derived from real data (stale offers, live
anomalies, seeded fraud clusters, risky merchants, expiring coupons, unmatched rows, unknown
compliance), so the log always matches what the app is actually doing.

## Task queue and priority engine

Tasks are generated, not hand-written: critical/high-risk merchants, duplicate products above 80 %
similarity, unknown compliance rules, broken affiliate links, price anomalies, fraud clusters, new
product candidates, action-required support tickets.

Priority = risk × 0.34 + traffic impact × 0.22 + revenue impact × 0.22 + compliance × 0.14 +
user impact × 0.08 → P0 ≥ 70, P1 ≥ 50, P2 ≥ 32, else P3.

## Merchant automations (`/merchant → Automations`)

Feed failure alert, price competitiveness alert, new review alert, coupon expiry reminder, stock
confidence warning — each with its own channel and on/off state.

Future service: **AutomationService** (rule store + evaluator on domain events + action dispatcher).
