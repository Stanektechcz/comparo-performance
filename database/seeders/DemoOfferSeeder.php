<?php

namespace Database\Seeders;

/**
 * Merchant listings, offers, coupons and coupon markets.
 */
class DemoOfferSeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->importer()->importOffers();
    }
}
