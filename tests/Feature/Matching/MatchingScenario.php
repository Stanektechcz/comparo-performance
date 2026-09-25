<?php

namespace Tests\Feature\Matching;

use App\Domain\Matching\Actions\MatchContext;
use App\Domain\Matching\Actions\MatchingActor;
use App\Domain\Matching\Actions\MatchListing;
use App\Domain\Matching\Actions\MatchOutcome;
use App\Domain\Matching\Contracts\ComplianceHoldCheck;
use App\Models\Brand;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\Product;
use App\Models\User;
use DateTimeImmutable;

/**
 * Builders for the Matching application-layer tests: products with explicit
 * brand/name/pack/EAN, and listings whose facts land in a known bucket under
 * the seeded `prototype-v1` policy (auto ≥ 90, confirm 65–89).
 */
final class MatchingScenario
{
    private static int $ean = 4000000000000;

    public static function product(string $brand = 'Acme Nutrition', string $name = 'Whey Isolate', string $pack = '900 g', array $attributes = []): Product
    {
        $brandModel = Brand::query()->where('name', $brand)->first() ?? Brand::factory()->create(['name' => $brand]);

        return Product::factory()->create([
            'brand_id' => $brandModel->id,
            'name' => $name,
            'pack_label' => $pack,
            'ean' => (string) ++self::$ean,
            ...$attributes,
        ]);
    }

    /**
     * An unmatched listing with no raw facts beyond the given ones.
     *
     * @param  array<string, mixed>  $facts
     */
    public static function listing(array $facts = [], ?Merchant $merchant = null): MerchantProduct
    {
        return MerchantProduct::factory()->unmatched()->create([
            'merchant_id' => $merchant->id ?? Merchant::factory(),
            'title' => 'Garden hose 20 m',
            'ean' => null,
            'brand_raw' => null,
            'pack_raw' => null,
            'variant_raw' => null,
            ...$facts,
        ]);
    }

    /**
     * Facts scoring ≥ 90 against the product: EAN + brand + title + pack.
     *
     * @return array<string, string|null>
     */
    public static function autoFacts(Product $product): array
    {
        return [
            'title' => "{$product->brand->name} {$product->name} {$product->pack_label}",
            'ean' => $product->ean,
            'brand_raw' => $product->brand->name,
            'pack_raw' => $product->pack_label,
        ];
    }

    /**
     * Facts scoring 65–89 against the product: EAN + brand, unrelated title, no pack.
     *
     * @return array<string, string|null>
     */
    public static function confirmFacts(Product $product): array
    {
        return [
            'title' => 'Protein powder vanilla',
            'ean' => $product->ean,
            'brand_raw' => $product->brand->name,
            'pack_raw' => null,
        ];
    }

    public static function context(?ComplianceHoldCheck $complianceHold = null, ?int $feedRunId = null, bool $force = false): MatchContext
    {
        return new MatchContext($feedRunId, $complianceHold, new DateTimeImmutable('2026-09-25 10:00:00'), $force);
    }

    public static function blocking(int ...$productIds): ComplianceHoldCheck
    {
        return new class($productIds) implements ComplianceHoldCheck
        {
            /**
             * @param  list<int>  $blocked
             */
            public function __construct(private readonly array $blocked) {}

            public function isBlocked(int $productId): bool
            {
                return in_array($productId, $this->blocked, true);
            }
        };
    }

    public static function match(MerchantProduct $listing, ?MatchContext $context = null): MatchOutcome
    {
        return app(MatchListing::class)->handle($listing->fresh(), $context ?? self::context());
    }

    public static function merchantActor(MerchantProduct $listing, ?User $user = null): MatchingActor
    {
        return MatchingActor::merchant($user ?? User::factory()->create(), $listing->merchant_id);
    }

    public static function staffActor(?User $user = null): MatchingActor
    {
        return MatchingActor::staff($user ?? User::factory()->create());
    }

    public static function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-25 11:00:00');
    }
}
