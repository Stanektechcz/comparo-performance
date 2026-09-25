# Agent registry

Persistent definitions live in [`.claude/agents/`](../../.claude/agents) (one file per specialist:
ownership, prohibited responsibilities, deliverables, review criteria). The orchestrator is the main
session. When a session cannot load project agents natively, the orchestrator instantiates the
equivalent built-in worker with the specialist's brief (column "Runtime binding").

| Agent | Kind | Runtime binding | Owns | Required reviewers |
|---|---|---|---|---|
| orchestrator | main session | — | task graph, integration, gates, commits, `docs/autonomy/*` | — |
| architecture-guardian | read-only | `task-plan` | — (reviews) | — |
| database-engineer | code | `task-deep` | `database/**` | architecture-guardian, performance-reviewer |
| feed-engineer | code | `task-deep` | `app/Domain/MerchantFeeds`, feed jobs/tests | security-reviewer, qa-parity-engineer |
| matching-engineer | code | `task-deep` | `app/Domain/Matching`, matching parity | qa-parity-engineer, architecture-guardian |
| pricing-engineer | code | `task-deep` | `app/Domain/Pricing` | qa-parity-engineer, architecture-guardian |
| search-engineer | code | `task-build` | `app/Domain/Search` | security-reviewer, performance-reviewer |
| account-engineer | code | `task-build` | account features | security-reviewer |
| messaging-engineer | code | `task-build` | notifications, mail | security-reviewer |
| reviews-orders-engineer | code | `task-deep` | reviews, orders | security-reviewer, qa-parity-engineer |
| affiliate-engineer | code | `task-deep` | `/go`, attribution | security-reviewer, architecture-guardian |
| merchant-platform-engineer | code | `task-build` | merchant HTTP + pages | security-reviewer, frontend-ux-engineer |
| admin-platform-engineer | code | `task-build` | staff HTTP + pages | security-reviewer, frontend-ux-engineer |
| community-engineer | code | `task-build` | community | security-reviewer |
| realtime-governance-engineer | code | `task-build` | live, governance | security-reviewer |
| growth-engineer | code | `task-build` | Growth OS | architecture-guardian |
| commercial-engineer | code | `task-deep` | Commercial OS | architecture-guardian, security-reviewer |
| billing-engineer | code | `task-deep` | billing | security-reviewer, commercial-engineer |
| seo-content-engineer | code | `task-build` | SEO, content | frontend-ux-engineer |
| security-reviewer | read-only | `ecc:security-reviewer` / `task-plan` | — (reviews every slice) | — |
| qa-parity-engineer | code (tests) | `task-build` | `tests/**` | architecture-guardian |
| frontend-ux-engineer | code | `task-build` | `resources/js/**`, `resources/css/**` | — |
| performance-reviewer | read-only | `task-review` | — (reviews data-heavy slices) | — |
| platform-engineer | code | `task-build` | CI, ops commands, runbooks | security-reviewer |
| docs-curator | code (docs) | `task-build` | `docs/**` | — |

## Rules

- No two code-writing agents own the same files at the same time (TASK-GRAPH "files" column).
- Critical security, billing, ranking or compliance code is never reviewed only by its author.
- Specialists never commit; the orchestrator integrates, runs gates and commits.
- 3–5 concurrent specialists at most; reviews are not duplicated.
