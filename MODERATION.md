# Moderation

One queue, one audit trail, seven content types.

## Queue tabs

| Tab | Contents |
| --- | --- |
| Reviews | New (`pending`) and reported (`flagged`) product and shop reviews |
| Community deals | User-submitted deals awaiting approval |
| Guides | Submitted guides awaiting publication |
| Forum posts | Threads and replies flagged by heuristics or reports |
| Reported users | Accounts with open reports |
| Merchant replies | Official responses that were reported or auto-flagged |
| Comments | Comments on reviews, guides and deals |

## Every item shows

- the content, in full, with its context (which product, shop, thread);
- the reason it is in the queue (new submission, report, heuristic);
- **risk flags** — the concrete signals, e.g. `caps 41 %`, `outbound link`, `no verified purchase`,
  `very short`, `generic praise`, `price not lower than original`, `first submission from this account`;
- the author's history: account age, reputation, published count, prior actions;
- the actions available for that type.

## Actions

`approve` · `reject` · `hide` · `warn` · `suspend` · `ban` · `escalate`, plus type-specific ones
(publish a guide, remove a merchant reply, mark a deal expired). Every action writes an
`audit_log` row: actor, action, entity, before/after diff, IP hash, timestamp. The log is
append-only — no deletes, no edits, including for administrators.

## Reporting

Users report reviews, comments, forum posts, users, merchant replies and deals. Reasons: Spam,
Harassment, Misleading, Fake review, Conflict of interest, Illegal content, Personal information,
Other, with an optional note. A confirmed report awards the reporter +5 reputation; repeatedly
false reports reduce the weight of that account's future reports.

## Publication rules

- A review is publicly readable only in status `approved`.
- A guide is publicly readable only in status `approved`; the author sees their own pending guide.
- A community deal is listed only in status `approved`.
- Hidden content stays addressable by moderators and keeps its audit history.

## Service levels

New reviews are targeted within 24 hours, reported content within 4 hours, and reported users
within 1 hour. The admin dashboard surfaces queue depth per type so the target is measurable
rather than aspirational.

## Trust & safety escalation

Escalation moves an item to a restricted queue with a written rationale: coordinated review
campaigns, threats, doxxing, and any content that touches legality (counterfeits, prohibited
substances, medical claims). Compliance-relevant escalations are cross-linked to the compliance
engine — see [COMPLIANCE.md](COMPLIANCE.md).

## Prototype

Sign in as `admin@comparo.app` / `admin1234` and open Admin → Moderation. Approving, rejecting
and hiding are live: they change what the public pages render and append entries to the audit log
you can inspect under Admin → Audit log.
