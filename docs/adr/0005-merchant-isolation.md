# ADR-0005: Merchant isolation and staff authorization

- Status: Accepted
- Date: 2026-09-25
- Related: C-20, D-29, `API-ENDPOINTS.md` §8–9, BACKEND-MIGRATION "Security and privacy"

## Context

Merchants see their own feeds, offers, analytics, invoices and benchmarks. Benchmarks use anonymised
category medians; nothing about another merchant may leak. Staff consoles need granular permissions
(the prototype models them as `cx.roles`, `comCan()`, `requirePerm()`). A single missing `where` clause
in a merchant query is a data breach.

## Decision

### Merchants

1. **Membership table** `merchant_user` (`merchant_id`, `user_id`, `role`; `unique(merchant_id, user_id)`).
   Roles (`App\Domain\Merchants\MerchantRole`): `owner`, `manager`, `analyst`.
   `MerchantRole::canManageOffers()` is true for owner and manager.
2. **Three layers, all required:**

   | Layer | Mechanism | Status |
   |---|---|---|
   | Route/controller | Policy per resource (`MerchantPolicy`, `OfferPolicy`, …) | planned (Phase 7) |
   | Data access | Merchant-scoped query objects that take the merchant id as a constructor argument | planned |
   | Serialization | Merchant API resources that never include other merchants' identities | planned |

3. **Negative tests are mandatory**: for every merchant endpoint, a test proves that merchant A's
   member receives 403/404 for merchant B's resource.
4. Staff access to merchant data goes through staff permissions, never through merchant membership.

### Staff

1. spatie/laravel-permission 8. The permission catalogue is the enum
   `App\Domain\Accounts\Authorization\Permission` (dot-separated values, e.g. `catalogue.merge`,
   `compliance.manage`, `ranking.configure`, `staff.horizon.view`).
2. Role bundles are `App\Domain\Accounts\Authorization\StaffRole` (14 roles, e.g. `super-admin`,
   `compliance-manager`, `seo-manager`, `finance`); each case maps to a permission list.
   `database/seeders/RolesAndPermissionsSeeder.php` syncs them.
3. **Checks always test permissions, never role names** (`$user->can('compliance.manage')`).
4. The Horizon dashboard (`/staff/horizon`, `HORIZON_PATH`) is gated by `staff.horizon.view`.
   `app/Providers/HorizonServiceProvider.php` defines `viewHorizon` as `$user->can(Permission::ViewHorizon->value)`.

## Consequences

- Adding a staff feature means adding a `Permission` case and assigning it to role bundles; there is no
  role-name branching to update.
- Query objects become the only way merchant-facing code reads merchant data; ad-hoc `Offer::query()`
  in merchant controllers is a review blocker.
- Internal signals (risk scores, fraud signals, commercial terms) are excluded at the resource layer for
  every non-staff audience.

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Global Eloquent scope keyed on "current merchant" | Implicit, easy to bypass with `withoutGlobalScopes`, leaks into queues/console |
| Role-name checks (`hasRole('admin')`) | Role bundles change; permissions are the stable contract |
| One `merchant_id` column on `users` | A user can belong to several merchants with different roles |
