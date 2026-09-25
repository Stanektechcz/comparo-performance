# Open decisions (autonomy log)

Business decisions D-01…D-29 (launch markets, VAT, affiliate networks, retention, …) live in
[`docs/architecture/open-decisions.md`](../architecture/open-decisions.md) with their safe defaults.
This file records decisions the orchestrator took autonomously under the safe-default policy
(least destructive, no external side effects, configurable, data-preserving, documented).
Revisit any of them by changing configuration or writing an ADR.

| ID | Date | Decision taken | Safe default / reversibility | Owner to confirm |
|---|---|---|---|---|
| A-01 | 2026-09-25 | Adopt the orchestrator's phase numbering (2 feeds … 19 release) | mapping kept in BACKLOG.md; roadmap doc left as history | CTO |
| A-02 | 2026-09-25 | No git worktrees for code-writing agents yet; disjoint file ownership on a phase branch instead | worktrees lack `vendor/` + `node_modules/`; revisit when CI caches exist | CTO |
| A-03 | 2026-09-25 | Commits carry the `Co-Authored-By: Claude` trailer; phase work on `phase-N/*` branches, fast-forwarded to `main` after Gate C | plain git history, no rewrites | repository owner |
| A-04 | 2026-09-25 | `CLAUDE.md`, `boost.json`, `.claude/agents/`, `.claude/launch.json` are versioned; `AGENTS.md`, `.mcp.json`, `.claude/skills` stay generated/ignored | `.gitignore` edit only | repository owner |
