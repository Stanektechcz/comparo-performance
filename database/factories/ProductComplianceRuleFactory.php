<?php

namespace Database\Factories;

use App\Domain\Compliance\ComplianceStatus;
use App\Models\Country;
use App\Models\Product;
use App\Models\ProductComplianceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductComplianceRule>
 */
class ProductComplianceRuleFactory extends Factory
{
    /**
     * Define the model's default state: a reviewed `allowed` rule.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'country_id' => Country::factory(),
            'status' => ComplianceStatus::Allowed,
            'reason' => 'No country-specific restriction on record.',
            'source' => 'Compliance review',
            'reviewed_by_user_id' => null,
            'reviewer_label' => 'legal@comparo',
            'reviewed_at' => now()->subMonth(),
        ];
    }

    public function status(ComplianceStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'reason' => $status === ComplianceStatus::Allowed ? $attributes['reason'] : "Marked {$status->label()} for this market.",
        ]);
    }
}
