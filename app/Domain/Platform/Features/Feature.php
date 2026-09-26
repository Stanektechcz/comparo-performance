<?php

namespace App\Domain\Platform\Features;

/**
 * A togglable capability, config-backed (A-17: wrapper hides the backend;
 * DB overrides / Pennant are future work, not this task).
 */
enum Feature: string
{
    case MerchantFeeds = 'merchant-feeds';
    case FeedUrlFetch = 'feed-url-fetch';
    case MatchingAutoPublish = 'matching-auto-publish';

    // ADR-0018 business-decision flags (D-xx). All default to false and none
    // are client-visible: each needs a human sign-off or contract before it
    // may ever be enabled (see config/features.php doc block).
    case BillingLiveInvoicing = 'billing-live-invoicing';
    case BillingStripe = 'billing-stripe';
    case AffiliateNetworkApi = 'affiliate-network-api';
    case DeliveryPromise = 'delivery-promise';
    case DeliveryPromise48h = 'delivery-promise-48h';
    case BuyerSubscriptions = 'buyer-subscriptions';
    case XpRedemption = 'xp-redemption';
    case MerchantCommissionDisplay = 'merchant-commission-display';
    case SelfServeAdBooking = 'self-serve-ad-booking';
    case DevelopersGateExempt = 'developers-gate-exempt';
    case VerificationForwardedEmail = 'verification-forwarded-email';
    case LiveRooms = 'live-rooms';

    /**
     * Whether this flag's state may be exposed to the client (shared Inertia
     * prop). Server-only flags (fetch guards, publishing behaviour) never
     * leak their state to the browser.
     */
    public function clientVisible(): bool
    {
        return $this === self::MerchantFeeds;
    }
}
