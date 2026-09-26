# Architecture Decision Records

Decisions for the Laravel 13 port of the Comparo Performance prototype. Each ADR records one decision,
its context, consequences and the alternatives that were rejected. ADRs are not rewritten after
acceptance; a changed decision gets a new ADR that supersedes the old one.

## Index

| ADR | Title | Status | Implementation |
|---|---|---|---|
| [0001](0001-laravel-modular-monolith.md) | Laravel modular monolith | Accepted | Phase 0 (in progress) |
| [0002](0002-canonical-product-identity.md) | Canonical product identity | Accepted | Phase 1 schema; matching in Phase 2 |
| [0003](0003-per-offer-price-history.md) | Per-offer, append-only price history | Accepted | Phase 1 (in progress) |
| [0004](0004-ranking-purity.md) | Ranking purity and versioned weights | Accepted | Phase 1 (in progress) |
| [0005](0005-merchant-isolation.md) | Merchant isolation and staff authorization | Accepted | Phase 1 schema/permissions; portal in Phase 7 |
| [0006](0006-billing-provider-abstraction.md) | Billing provider abstraction | Accepted | Phase 10 (not started) |
| [0007](0007-compliance-before-serialization.md) | Compliance before serialization | Accepted | Phase 1 (in progress) |
| [0008](0008-market-locale-currency.md) | Market, locale and currency are independent | Accepted | Phase 1 (in progress) |
| [0009](0009-money-minor-units.md) | Money as integer minor units | Accepted | Phase 1 (implemented in domain) |
| [0010](0010-prototype-parity-harness.md) | Prototype parity harness | Accepted | Phase 0 (implemented for 7 engines) |
| [0011](0011-local-development-without-docker.md) | Local development without Docker | Accepted | Phase 0 |
| [0012](0012-canonical-matching-engine.md) | Canonical matching engine | Accepted | Phase 2 (parity verified) |
| [0013](0013-merchant-feed-ingestion-pipeline.md) | Merchant feed ingestion pipeline (amends ADR-0003) | Accepted | Phase 2 (functional) |
| [0014](0014-audit-logging.md) | Audit logging | Accepted | Phase 2 (functional) |
| [0015](0015-domain-events-feature-flags-merchant-context.md) | Domain events, feature flags and merchant context | Accepted | Phase 2 (functional) |
| [0016](0016-search-and-discovery.md) | Search and discovery | Accepted | Phase 3 (functional; local-engine relevance parity verified) |
| [0017](0017-multi-currency-comparison.md) | Multi-currency total-price comparison | Accepted | Phase 3 (functional; single-currency paths parity verified) |
| [0018](0018-business-decisions-baseline.md) | Business decisions baseline (D-01…D-29) | Accepted | Decisions only; planned config/flags applied per phase; legal/DPO/tax sign-off pending before production |

## Template

```markdown
# ADR-NNNN: Title

- Status: Proposed | Accepted | Superseded by ADR-XXXX
- Date: YYYY-MM-DD
- Related: other ADRs, docs/architecture/*.md blocks (C-xx, D-xx)

## Context
## Decision
## Consequences
## Alternatives considered
```

## Rules

- A decision that changes a ported algorithm needs an ADR **and** parity evidence (ADR-0010).
- Cross-references: `C-xx` = [prototype-contradictions.md](../architecture/prototype-contradictions.md),
  `D-xx` = [open-decisions.md](../architecture/open-decisions.md).
- "Planned" in an ADR means the code path does not exist yet.
