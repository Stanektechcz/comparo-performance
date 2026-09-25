<?php

namespace App\Domain\Accounts\Authorization;

/**
 * The staff permission catalogue.
 *
 * Authorization checks always test a permission, never a role name. Roles
 * (see StaffRole) are only bundles of these permissions. Merchant-side access
 * is not granted through this catalogue: it is scoped by merchant membership
 * (see docs/adr/0005-merchant-isolation.md).
 */
enum Permission: string
{
    case AccessStaffConsole = 'staff.access';
    case ViewHorizon = 'staff.horizon.view';

    case ViewCatalogue = 'catalogue.view';
    case ManageCatalogue = 'catalogue.manage';
    case MergeProducts = 'catalogue.merge';

    case ViewMerchants = 'merchants.view';
    case ApproveMerchants = 'merchants.approve';
    case SuspendMerchants = 'merchants.suspend';
    case ViewMerchantRisk = 'merchants.risk.view';

    case ManageOffers = 'offers.manage';
    case ManageFeeds = 'feeds.manage';
    case ReviewMatching = 'matching.review';
    case ReviewPriceAnomalies = 'pricing.anomalies.review';

    case ViewCompliance = 'compliance.view';
    case ManageCompliance = 'compliance.manage';

    case ModerateReviews = 'reviews.moderate';
    case DecidePurchaseProofs = 'verification.decide';
    case ModerateCommunity = 'community.moderate';

    case ManageSeo = 'seo.manage';

    case ManageAffiliate = 'affiliate.manage';
    case ViewAffiliateReports = 'affiliate.reports.view';

    case ViewAnalytics = 'analytics.view';
    case ViewGrowth = 'growth.view';
    case ManageGrowth = 'growth.manage';

    case ViewSupportUsers = 'support.users.view';

    case ConfigureRanking = 'ranking.configure';
    case ManageFeatureFlags = 'platform.feature-flags.manage';
    case ManageSettings = 'platform.settings.manage';
    case ViewAuditLog = 'platform.audit.view';
    case ManageRoles = 'platform.roles.manage';

    case ViewCommercial = 'commercial.view';
    case ManagePlans = 'commercial.plans.manage';
    case ManageSubscriptions = 'commercial.subscriptions.manage';
    case ManageInvoices = 'billing.invoices.manage';
    case IssueCreditNotes = 'billing.credit-notes.issue';
    case ApproveCampaigns = 'commercial.campaigns.approve';
    case ManageCampaigns = 'commercial.campaigns.manage';
    case ManageSalesPipeline = 'sales.pipeline.manage';
    case ManageAccounts = 'sales.accounts.manage';
    case ManageRenewals = 'commercial.renewals.manage';
    case ManageApiProducts = 'commercial.api-products.manage';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $permission): string => $permission->value, self::cases());
    }
}
