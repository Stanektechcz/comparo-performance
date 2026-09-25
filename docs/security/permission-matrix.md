# Permission matrix

Ground truth: `app/Domain/Accounts/Authorization/Permission.php`, `StaffRole.php`,
`app/Domain/Merchants/MerchantRole.php`, `app/Policies/{FeedSourcePolicy,MerchantProductPolicy,
OfferPolicy}.php` (2026-09-25). Staff authorization always tests a **permission**, never a role name
directly (ADR-0005); roles are named bundles of permissions.

## Staff permissions × roles (Phase 2 relevant subset)

Full permission catalogue exists (`Permission` has 30+ cases covering commercial/billing/community/SEO
etc.); this table shows the permissions Phase 2 routes/policies actually check, and which of the 14
staff roles grant them. `SuperAdmin` grants every `Permission::cases()` and is omitted below (always ✓).

| Permission | Marketplace Admin | Merchant Manager | Compliance Manager | Analyst | Support |
|---|---|---|---|---|---|
| `staff.access` (`AccessStaffConsole`) | ✓ | ✓ | ✓ | ✓ | ✓ |
| `staff.horizon.view` (`ViewHorizon`) | ✓ | | | | |
| `catalogue.view` (`ViewCatalogue`) | ✓ | ✓ | ✓ | ✓ | ✓ |
| `catalogue.manage` (`ManageCatalogue`) | ✓ | | | | |
| `merchants.view` (`ViewMerchants`) | ✓ | ✓ | | ✓ | ✓ |
| `offers.manage` (`ManageOffers`) | ✓ | ✓ | | | |
| `feeds.manage` (`ManageFeeds`) | ✓ | ✓ | | | |
| `matching.review` (`ReviewMatching`) | ✓ | ✓ | | | |
| `pricing.anomalies.review` (`ReviewPriceAnomalies`) | ✓ | | | | |
| `compliance.view` (`ViewCompliance`) | ✓ | | ✓ | | |
| `compliance.manage` (`ManageCompliance`) | | | ✓ | | |
| `platform.audit.view` (`ViewAuditLog`) | ✓ | | ✓ | | |
| `analytics.view` (`ViewAnalytics`) | ✓ | | | ✓ | |

Other roles not shown (`SeoManager`, `CommunityModerator`, `AffiliateManager`, `CommercialAdmin`,
`SalesManager`, `AccountManager`, `Finance`, `CampaignManager`) grant none of the Phase 2 permissions
above — they hold only their own domain's permissions (SEO, moderation, affiliate, commercial/billing).

Routes gated by these permissions: see [route-matrix.md](../architecture/route-matrix.md) §"Staff
console". `admin/catalogue/matching/listings/{listing}/rematch` additionally requires `offers.manage`
on top of `matching.review`, because relinking a *published* listing is an offers-visibility change,
not just a matching decision.

## Merchant roles × merchant actions

`MerchantRole` (`merchant_user.role`): `owner`, `manager`, `analyst`. Access is always scoped to the
merchants a user belongs to (`user->isMemberOf($merchantId)`), resolved through the active
`MerchantContext` (ADR-0015) — never a global staff-style permission.

| Action | Owner | Manager | Analyst |
|---|---|---|---|
| View feed sources, runs, errors | ✓ | ✓ | ✓ |
| Create / configure / pause / resume a feed (`canManageFeeds()`) | ✓ | ✓ | |
| Run a feed manually, cancel a run, upload a file | ✓ | ✓ | |
| View / change feed credentials (`canManageFeedCredentials()`) | ✓ | | |
| View matching queue / listing detail | ✓ | ✓ | ✓ |
| Decide a match (confirm/choose/reject), propose a new product (`canManageFeeds()`, reused by `MerchantProductPolicy::decide`/`propose`) | ✓ | ✓ | |
| Manage published offers directly (`canManageOffers()`) | ✓ | ✓ | |

Staff never act through the merchant portal; the staff console (`/admin/catalogue/matching`) is the
only place staff review/relink matches, gated by `matching.review`/`offers.manage` above, independent of
any merchant membership.

## Isolation vs. authorization (ADR-0005, ADR-0015)

Every merchant route resolves its resource through a query scoped to the *active* `MerchantContext`
merchant first — a valid id belonging to a merchant the user is **not** currently acting as returns
**404**, not 403, so existence of the id is never leaked. The policy check above only runs once the
resource has already resolved inside the active merchant. Negative tests cover both layers, including a
user who is a member of two merchants (A and B) confirming a B-owned id 404s while acting as A.
