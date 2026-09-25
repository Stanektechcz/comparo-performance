# ADR-0006: Billing provider abstraction

- Status: Accepted (decision now; implementation Phase 10)
- Date: 2026-09-25
- Related: D-04, D-05, `BILLING.md`, `INVOICING.md`, `SUBSCRIPTIONS.md`, BACKEND-MIGRATION "Billing provider abstraction"

## Context

Commercial OS sells merchant subscriptions, add-ons, sponsored campaigns and API plans. Enterprise
contracts are often invoiced manually. The payment provider and whether to charge at launch are open
(D-05); VAT determination is open and needs specialist review (D-04). The prototype models tax states
(tax-exclusive, tax-inclusive, VAT rate, reverse charge) but does not determine tax.

## Decision

1. Business logic depends on an interface, never on a provider SDK:

   ```php
   interface BillingProvider
   {
       public function createCustomer(Merchant $merchant): string;
       public function createSubscription(Merchant $merchant, PlanVersion $plan, BillingCycle $cycle): Subscription;
       public function changePlan(Subscription $subscription, PlanVersion $plan): ProrationResult;
       public function cancel(Subscription $subscription, bool $atPeriodEnd): void;
       public function issueInvoice(Invoice $invoice): void;
       public function handleWebhook(Request $request): void;
   }
   ```

2. Two implementations ship together in Phase 10:

   | Implementation | Use |
   |---|---|
   | `StripeBillingProvider` | Card/SEPA subscriptions via Stripe Checkout/Elements; webhooks |
   | `ManualInvoiceProvider` | Enterprise and manually invoiced contracts; no external calls |

   The provider is chosen per billing profile, not globally.
3. **The Stripe SDK is referenced only inside `StripeBillingProvider`** (and its webhook handler).
   An architecture test will forbid `Stripe\` imports elsewhere.
4. **Tax determination is separate from billing state.** A `TaxDeterminer` port (planned) decides rate,
   reverse charge and VIES validation; billing stores the resulting tax lines. Until D-04 is decided, no
   invoice is issued to a real customer.
5. No card data touches the application (Stripe-hosted fields only). Money follows ADR-0009.

## Consequences

- Manual invoicing is a first-class path, not a later rewrite.
- Switching or adding a provider (for example after D-05) is one new class plus configuration.
- Proration, dunning and invoice numbering rules live in the Commercial context and are testable without
  network calls (fake provider in tests).
- Nothing in this ADR is implemented yet; `app/Domain/Commercial` does not exist.

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Laravel Cashier (Stripe) directly on models | Couples subscription state to one provider; manual invoicing becomes a special case |
| Provider-agnostic billing SaaS only | Still needs a manual path for enterprise contracts; adds a dependency before D-05 |
| Compute VAT inside billing | Tax rules need jurisdiction review and change independently of billing |
