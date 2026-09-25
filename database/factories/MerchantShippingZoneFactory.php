<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Merchant;
use App\Models\MerchantShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantShippingZone>
 */
class MerchantShippingZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'country_id' => Country::factory(),
            'cost_minor' => 490,
            'currency' => 'EUR',
            'min_days' => 2,
            'max_days' => 4,
            'carrier' => 'DHL',
            'duties_apply' => false,
        ];
    }
}
