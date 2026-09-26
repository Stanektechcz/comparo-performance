# External dependencies

Absence of credentials never blocks development: every integration gets an adapter interface,
configuration, a fake/sandbox implementation, contract tests and local fixtures.

Statuses: `IMPLEMENTABLE_LOCALLY` · `SANDBOX_AVAILABLE` · `CREDENTIAL_REQUIRED` · `PRODUCTION_ONLY` · `OPTIONAL`.

| Dependency | Purpose | Local/dev strategy | Status | Human action needed | Open decision |
|---|---|---|---|---|---|
| PostgreSQL 16+ | primary database | SQLite in tests; embedded-postgres for verification; CI service | IMPLEMENTABLE_LOCALLY | production instance | — |
| Redis | Horizon queues, cache, click buffer | database/array drivers locally; Redis-only tests conditional | PRODUCTION_ONLY | production instance | — |
| Meilisearch | search engine | Scout `collection`/`database` driver in tests | IMPLEMENTABLE_LOCALLY (binary optional) | production master key | — |
| S3-compatible object storage | private uploads (feeds, receipts), exports | `local` private disk | CREDENTIAL_REQUIRED | bucket + keys | — |
| Transactional email provider | notifications, verification | `log`/`array` mailer, Mailpit when available | CREDENTIAL_REQUIRED | provider account | D-27 |
| Inbound email provider | forwarded-order verification | signed local webhook simulator | CREDENTIAL_REQUIRED | provider account | D-27 |
| Billing provider (Stripe) | subscriptions, invoices | fake provider adapter; Stripe test mode needs test keys | CREDENTIAL_REQUIRED | account + test keys | D-05 |
| Affiliate networks | conversions, reconciliation | sandbox adapter + fixture webhooks | CREDENTIAL_REQUIRED | network accounts | D-03 |
| Merchant feed endpoints | real merchant catalogues | fixture files + `Http::fake()` | PRODUCTION_ONLY | merchant onboarding | D-07 |
| FX rates (ECB) | currency conversion | fixed prototype rates | IMPLEMENTABLE_LOCALLY (public feed, no key) | — | D-06 |
| OAuth providers | social sign-in | not planned yet | OPTIONAL | — | — |
| CAPTCHA | abuse protection | rate limits + honeypot | OPTIONAL | provider keys if chosen | — |
| Observability (Sentry / Nightwatch) | error + performance monitoring | log channels | OPTIONAL | account | — |
| WebSocket transport (Reverb) | live rooms | transport abstraction + polling fallback | IMPLEMENTABLE_LOCALLY | — | D-28 |
| Git remote + GitHub Actions | CI execution | remote `Stanektechcz/comparo-performance` (private) + `.github/workflows/tests.yml` (SQLite, PostgreSQL 16 + Meilisearch 1.53.2 service, frontend) | RESOLVED | — | — |
| Hosted staging server (domain, TLS) | Phase 4+ staging environment (A-39) | `tools/staging/{smoke,local-stack}.mjs` runs the stack locally | CREDENTIAL_REQUIRED | hosting decision + credentials | A-39 |
| Error tracking vendor | production error/performance monitoring | log channels | CREDENTIAL_REQUIRED | vendor + account approval | — |
| Mail provider | production transactional email | `log`/`array` mailer locally | CREDENTIAL_REQUIRED | provider + account approval | D-27 |
