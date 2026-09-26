<?php

namespace App\Domain\Search\Benchmark;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Country;
use App\Models\Merchant;
use App\Models\MerchantShippingZone;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Builds a synthetic catalogue in the currently-default connection (the
 * throwaway {@see BenchmarkDatabase}) sized for
 * `comparo:benchmark:search-indexing`: every market is EUR (the first
 * `CountryFactory::MARKETS` entries), so no exchange-rate setup is needed
 * and `comparo.comparison_currency` (EUR) always converts for free.
 *
 * A handful of brands/categories/merchants are reused across every product
 * so the catalogue stays a synthetic-but-representative shape rather than
 * one row per relation, and every merchant ships to every synthetic market.
 */
final class SyntheticCatalog
{
    private const int BRANDS = 20;

    private const int CATEGORIES = 8;

    private const int MERCHANTS = 15;

    /**
     * @return list<string> the activated market ISO codes
     */
    public function activateMarkets(int $count): array
    {
        $countries = Country::factory()->count($count)->create();

        return array_values(array_map(static fn (Country $country): string => (string) $country->code, $countries->all()));
    }

    /**
     * @return list<int> the created product ids, in creation order
     */
    public function buildProducts(int $count): array
    {
        $brands = Brand::factory()->count(self::BRANDS)->create();
        $categories = Category::factory()->count(self::CATEGORIES)->create();
        $merchants = $this->buildMerchants();

        $ids = [];
        foreach (self::chunks($count, 200) as $chunkSize) {
            $products = Product::factory()
                ->count($chunkSize)
                ->sequence(fn ($sequence): array => [
                    'brand_id' => $brands[$sequence->index % $brands->count()]->id,
                    'category_id' => $categories[$sequence->index % $categories->count()]->id,
                ])
                ->create();

            foreach ($products as $product) {
                $ids[] = $product->id;
                $this->buildOffers($product, $merchants);
            }
        }

        return $ids;
    }

    /**
     * @return Collection<int, Merchant>
     */
    private function buildMerchants(): Collection
    {
        $merchants = Merchant::factory()->count(self::MERCHANTS)->create();
        $countries = Country::query()->where('is_active', true)->get();

        foreach ($merchants as $merchant) {
            foreach ($countries as $country) {
                MerchantShippingZone::factory()->create([
                    'merchant_id' => $merchant->id,
                    'country_id' => $country->id,
                ]);
            }
        }

        return $merchants;
    }

    /**
     * Two to four offers per product from distinct merchants, priced around
     * the product's RRP so ranking/pricing has a real spread to compare.
     *
     * @param  Collection<int, Merchant>  $merchants
     */
    private function buildOffers(Product $product, Collection $merchants): void
    {
        $offerCount = random_int(2, 4);
        $sellers = $merchants->random(min($offerCount, $merchants->count()));

        foreach ($sellers as $merchant) {
            Offer::factory()->create([
                'product_id' => $product->id,
                'merchant_id' => $merchant->id,
                'price_minor' => (int) round($product->rrp_minor * random_int(85, 110) / 100),
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private static function chunks(int $total, int $size): array
    {
        $chunks = array_fill(0, intdiv($total, $size), $size);
        $remainder = $total % $size;
        if ($remainder > 0) {
            $chunks[] = $remainder;
        }

        return $chunks === [] ? [] : $chunks;
    }
}
