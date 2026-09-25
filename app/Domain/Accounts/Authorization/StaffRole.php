<?php

namespace App\Domain\Accounts\Authorization;

/**
 * Staff roles as named bundles of permissions.
 *
 * The prototype simulated these with a demo role switcher (/intel → Roles);
 * here a role is bound to the authenticated account and every check is made
 * against the permissions it grants.
 */
enum StaffRole: string
{
    case SuperAdmin = 'super-admin';
    case MarketplaceAdmin = 'marketplace-admin';
    case MerchantManager = 'merchant-manager';
    case ComplianceManager = 'compliance-manager';
    case SeoManager = 'seo-manager';
    case CommunityModerator = 'community-moderator';
    case AffiliateManager = 'affiliate-manager';
    case Analyst = 'analyst';
    case Support = 'support';
    case CommercialAdmin = 'commercial-admin';
    case SalesManager = 'sales-manager';
    case AccountManager = 'account-manager';
    case Finance = 'finance';
    case CampaignManager = 'campaign-manager';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::MarketplaceAdmin => 'Marketplace Admin',
            self::MerchantManager => 'Merchant Manager',
            self::ComplianceManager => 'Compliance Manager',
            self::SeoManager => 'SEO Manager',
            self::CommunityModerator => 'Community Moderator',
            self::AffiliateManager => 'Affiliate Manager',
            self::Analyst => 'Analyst',
            self::Support => 'Support',
            self::CommercialAdmin => 'Commercial Admin',
            self::SalesManager => 'Sales Manager',
            self::AccountManager => 'Account Manager',
            self::Finance => 'Finance',
            self::CampaignManager => 'Campaign Manager',
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        $staff = [Permission::AccessStaffConsole];

        return match ($this) {
            self::SuperAdmin => Permission::cases(),
            self::MarketplaceAdmin => [
                ...$staff,
                Permission::ViewHorizon,
                Permission::ViewCatalogue,
                Permission::ManageCatalogue,
                Permission::MergeProducts,
                Permission::ViewMerchants,
                Permission::ApproveMerchants,
                Permission::SuspendMerchants,
                Permission::ViewMerchantRisk,
                Permission::ManageOffers,
                Permission::ManageFeeds,
                Permission::ReviewMatching,
                Permission::ReviewPriceAnomalies,
                Permission::ViewCompliance,
                Permission::ModerateReviews,
                Permission::DecidePurchaseProofs,
                Permission::ViewAnalytics,
                Permission::ViewAuditLog,
            ],
            self::MerchantManager => [
                ...$staff,
                Permission::ViewCatalogue,
                Permission::ViewMerchants,
                Permission::ApproveMerchants,
                Permission::ViewMerchantRisk,
                Permission::ManageFeeds,
                Permission::ReviewMatching,
                Permission::ManageOffers,
            ],
            self::ComplianceManager => [
                ...$staff,
                Permission::ViewCatalogue,
                Permission::ViewCompliance,
                Permission::ManageCompliance,
                Permission::ViewAuditLog,
            ],
            self::SeoManager => [
                ...$staff,
                Permission::ViewCatalogue,
                Permission::ManageSeo,
                Permission::ViewAnalytics,
            ],
            self::CommunityModerator => [
                ...$staff,
                Permission::ModerateReviews,
                Permission::DecidePurchaseProofs,
                Permission::ModerateCommunity,
            ],
            self::AffiliateManager => [
                ...$staff,
                Permission::ViewMerchants,
                Permission::ManageAffiliate,
                Permission::ViewAffiliateReports,
            ],
            self::Analyst => [
                ...$staff,
                Permission::ViewCatalogue,
                Permission::ViewMerchants,
                Permission::ViewAnalytics,
                Permission::ViewGrowth,
                Permission::ViewAffiliateReports,
                Permission::ViewCommercial,
            ],
            self::Support => [
                ...$staff,
                Permission::ViewCatalogue,
                Permission::ViewMerchants,
                Permission::ViewSupportUsers,
            ],
            self::CommercialAdmin => [
                ...$staff,
                Permission::ViewCommercial,
                Permission::ManagePlans,
                Permission::ManageSubscriptions,
                Permission::ManageInvoices,
                Permission::IssueCreditNotes,
                Permission::ApproveCampaigns,
                Permission::ManageCampaigns,
                Permission::ManageSalesPipeline,
                Permission::ManageAccounts,
                Permission::ManageRenewals,
                Permission::ManageApiProducts,
            ],
            self::SalesManager => [
                ...$staff,
                Permission::ViewCommercial,
                Permission::ManageSalesPipeline,
                Permission::ManageAccounts,
                Permission::ManageRenewals,
            ],
            self::AccountManager => [
                ...$staff,
                Permission::ViewCommercial,
                Permission::ManageAccounts,
                Permission::ManageRenewals,
            ],
            self::Finance => [
                ...$staff,
                Permission::ViewCommercial,
                Permission::ManageInvoices,
                Permission::IssueCreditNotes,
                Permission::ViewAffiliateReports,
            ],
            self::CampaignManager => [
                ...$staff,
                Permission::ViewCommercial,
                Permission::ManageCampaigns,
                Permission::ApproveCampaigns,
                Permission::ViewCompliance,
            ],
        };
    }
}
