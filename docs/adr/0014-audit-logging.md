# ADR-0014: Audit logging

- Status: Accepted
- Date: 2026-09-25
- Related: `docs/architecture/phase-2-feeds-matching.md` §1, §8; ADR-0005, ADR-0012, ADR-0013

## Context

Phase 2 introduces privileged, merchant- and staff-triggered actions that change data other people rely
on (feed credentials, matching decisions, product candidates). These need a tamper-evident record of
who did what, without turning the audit log itself into a privacy liability or a place secrets can leak.

## Decision

1. **`App\Domain\Platform\Audit\AuditLogger::record()` is called inside the caller's own database
   transaction**, immediately next to the write it describes — never from a listener or a queued job
   dispatched separately. If the action rolls back, its audit row rolls back with it; an action can
   never commit without one. Callers: `Feeds\Actions\{CreateFeedSource,UpdateFeedSource,
   ChangeFeedSourceStatus,UpdateFeedCredentials,SaveFeedMapping,StartFeedRun,CancelFeedRun}`,
   `Matching\Actions\{DecideMatch,Rematch,ResolveConflict,ProposeProductCandidate,
   ResolveProductCandidate}`.
2. **Actions are a closed, append-only enum** (`AuditAction`, format `{subject}.{verb}`, values never
   renamed once written): `feed_source.created/updated/status_changed/credentials_changed/
   mapping_changed/deleted`, `feed_run.started_manually/cancelled`, `matching.decided/rematched/
   unlinked/conflict_resolved`, `product_candidate.proposed/resolved`.
3. **Redaction is automatic, not opt-in.** `AuditRedactor::redact()` walks `before`/`after` (max depth
   16) and replaces any value whose key matches `/password|secret|token|credential|api_?key|
   authorization|cookie/i` — case-insensitive, anywhere in the key, including nested structures under
   that key — with `[redacted]`. Booleans survive (e.g. `credentials_changed: true` carries no secret
   itself). Objects are flattened to arrays first so secrets nested inside DTOs are still caught. The
   redactor never throws: a logging bug must not block the action it is recording.
4. **Actors** (`AuditActor`) are typed: a human actor carries a user id; a system actor
   (`isSystem()`) carries no id and instead stores its component name under
   `AuditLogger::SYSTEM_COMPONENT_KEY` inside `after`, because `audit_logs` has no dedicated system-actor
   column. This lets scheduled/console-triggered actions (e.g. staff-initiated reconciliation) still be
   attributed without inventing a fake user row.
5. **Correlation id, IP and user agent are ambient, not parameters.** `correlation_id` comes from
   `Context::get('correlation_id')`, set once per request by `AssignCorrelationId`; `ip_address`/
   `user_agent` come from **hidden** `Context` values (`Context::getHidden`), kept out of `Context`'s
   normal dump/log output. Outside an HTTP request (console commands, the scheduler) all three are
   null. Queued jobs inherit the request's values automatically, because Laravel propagates `Context`
   into dispatched jobs — a feed-run action triggered by a merchant click and finished by a queued job
   still carries that request's correlation id.
6. **The audit log is pseudonymous by design**: callers pass ids, never emails or names, into
   `before`/`after`.
7. **The `audit_logs` actor foreign key is decoupled from `users` (R3).** Migration
   `2026_09_25_100750_decouple_audit_log_actor_from_users` removes the FK from `audit_logs.actor_id` to
   `users.id`. The same reasoning drives `matching_decisions.decided_by_user_id`, which was designed
   with no FK from the start (migration `2026_09_25_101300`). **Reason:** account deletion (GDPR
   erasure, or ordinary user removal) must never be blocked by, or cascade-destroy, the append-only
   history it participated in. An FK would force a choice between "can't delete the user" and
   "silently rewrite/null history on delete" — both wrong for an audit trail. Decoupling keeps the
   numeric actor id in place (so the pseudonymous record is intact and correlatable) while letting the
   `users` row itself be deleted independently.

## Consequences

- Every privileged Phase 2 mutation is reconstructable: who (actor id + component), what
  (`AuditAction` + subject morph), when, from where (correlation id), with before/after state minus
  secrets.
- Losing the FK means an application-level orphan (`actor_id` pointing at a deleted user) is possible
  by design; reports that join `audit_logs` to `users` must outer-join and expect nulls.
- Because redaction is key-pattern based, a caller that names a sensitive field unconventionally (not
  matching the pattern) could leak it; the review checklist for new `AuditLogger::record()` call sites
  must check field names against the pattern, not just eyeball the value.

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Write the audit row from an after-commit listener on each domain event | Same divergence risk as ADR-0013's price-snapshot decision: the row could commit out of order with, or after a crash separately from, the action it describes |
| Keep the FK to `users` and soft-delete users instead | Soft-delete is a broader, cross-cutting decision this domain shouldn't force; still leaves the erasure problem for hard-delete/anonymisation requests |
| Store actor email/name for readability | Breaks the pseudonymity goal and duplicates data that can change or be erased independently of the log |
