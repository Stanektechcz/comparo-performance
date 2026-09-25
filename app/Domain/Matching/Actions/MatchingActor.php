<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Exceptions\ListingOutsideMerchantScope;
use App\Domain\Matching\Exceptions\MatchDecisionNotAllowed;
use App\Models\MerchantProduct;
use App\Models\User;

/**
 * The person behind a manual matching action: a merchant user acting for one
 * merchant, or staff. The HTTP layer authorises (merchant membership and role,
 * permission `matching.review`); the actions re-check the merchant scope as
 * defence in depth and refuse staff-only operations to merchant actors.
 */
final readonly class MatchingActor
{
    private function __construct(
        public User $user,
        public ?int $merchantId,
    ) {}

    public static function merchant(User $user, int $merchantId): self
    {
        return new self($user, $merchantId);
    }

    public static function staff(User $user): self
    {
        return new self($user, null);
    }

    public function isStaff(): bool
    {
        return $this->merchantId === null;
    }

    /**
     * @throws ListingOutsideMerchantScope
     */
    public function assertCanActOn(MerchantProduct $listing): void
    {
        if ($this->merchantId !== null && $listing->merchant_id !== $this->merchantId) {
            throw ListingOutsideMerchantScope::forListing($listing->id, $this->merchantId);
        }
    }

    /**
     * @throws MatchDecisionNotAllowed
     */
    public function assertStaff(string $action): void
    {
        if (! $this->isStaff()) {
            throw MatchDecisionNotAllowed::staffOnly($action);
        }
    }
}
