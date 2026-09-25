# Billing

## Billing profile

Legal name, company ID, VAT ID, billing address, country, billing email, currency — per account.

## Tax model

The prototype models tax-exclusive and tax-inclusive pricing, a VAT rate per billing country, and a
reverse-charge state for cross-border EU accounts.

**This is not tax advice.** A production system needs jurisdiction-specific validation: place-of-supply
rules, VIES VAT-number verification, digital-services thresholds, US sales-tax nexus, and invoice
content requirements per country. The prototype deliberately stops at modelling the shape.

## Payment methods

Card, SEPA, bank transfer and invoice terms are represented as future-ready options with demo detail
strings. **No real payment credential is ever collected or stored in the browser prototype.**

## Provider abstraction

Nothing is coupled to one provider. A production build implements a billing adapter behind
`BillingService`; Stripe, Adyen, Paddle and manual invoicing all satisfy the same interface.
Future billing events: `subscription.created`, `subscription.updated`, `invoice.created`,
`invoice.paid`, `invoice.failed`, `subscription.cancelled`.

## Currency and rounding

Reporting normalises into EUR at a dated demo FX snapshot (labelled as such); each invoice keeps its
source currency. Rounding is currency-aware at two decimals for EUR-like currencies.

## Audit

Plan changes, discounts applied, invoices voided, subscriptions cancelled, credits created and
contract changes are all written to the audit log with actor, entity and timestamp.
