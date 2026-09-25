<?php

use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Offers\Events\OfferDeactivated;
use App\Domain\Offers\Events\OfferPublished;
use App\Domain\Offers\Events\OfferRelinked;
use App\Domain\Pricing\Events\PriceChanged;
use App\Domain\Search\Jobs\ProcessSearchOutbox;
use App\Domain\Search\Jobs\QueueFullSearchReindex;
use App\Models\Brand;
use App\Models\BrandAlias;
use App\Models\Category;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\Ingredient;
use App\Models\Merchant;
use App\Models\MerchantRiskEvent;
use App\Models\MerchantShippingZone;
use App\Models\MerchantTrustSignal;
use App\Models\Offer;
use App\Models\Product;
use App\Models\SearchIndexOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CatalogScenario;

/**
 * Every trigger of docs/architecture/phase-3-search.md §5 writes the expected
 * search_index_outbox rows (after commit); ProcessSearchOutbox is faked.
 */

/**
 * @return list<string> "entity:id" per outbox row, "!" marks priority
 */
function outboxEntries(): array
{
    return SearchIndexOutbox::query()->orderBy('entity')->orderBy('entity_id')->get()
        ->map(static fn (SearchIndexOutbox $row): string => $row->entity->value.':'.$row->entity_id.($row->priority ? '!' : ''))
        ->all();
}

function clearOutbox(): void
{
    SearchIndexOutbox::query()->delete();
}

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
});

it('queues the product of an offer or price event', function (Closure $makeEvent) {
    Queue::fake([ProcessSearchOutbox::class]);

    event($makeEvent());

    expect(outboxEntries())->toBe(['product:7']);
    Queue::assertNotPushed(ProcessSearchOutbox::class);
})->with([
    'offer published' => [fn () => new OfferPublished(1, 1, 7, 1, 'created', null)],
    'offer deactivated' => [fn () => new OfferDeactivated(1, 7, 1, 'manual')],
    'price changed' => [fn () => new PriceChanged(1, 7, 1, 3000, 2500, 'EUR', 'EUR', 'price_change', null)],
]);

it('queues the previous and the new product of a relink', function () {
    Queue::fake([ProcessSearchOutbox::class]);

    event(new OfferRelinked(1, 1, 1, 7, 8));

    expect(outboxEntries())->toBe(['product:7', 'product:8']);
});

it('merges repeated triggers into one row per entity', function () {
    Queue::fake([ProcessSearchOutbox::class]);

    event(new OfferPublished(1, 1, 7, 1, 'created', null));
    event(new PriceChanged(1, 7, 1, 3000, 2500, 'EUR', 'EUR', 'price_change', null));
    event(new OfferRelinked(1, 1, 1, 7, 8));

    expect(outboxEntries())->toBe(['product:7', 'product:8']);
});

it('queues the product with priority and dispatches the priority run when a compliance rule changes', function (Closure $change) {
    $catalog = CatalogScenario::create();
    $product = $catalog->product();
    $rule = $catalog->allow($product);
    clearOutbox();
    Queue::fake([ProcessSearchOutbox::class]);

    $change($rule);

    expect(outboxEntries())->toBe(["product:{$product->id}!"]);
    Queue::assertPushed(ProcessSearchOutbox::class, 1);
    Queue::assertPushed(ProcessSearchOutbox::class, fn (ProcessSearchOutbox $job): bool => $job->priorityOnly && $job->queue === 'search');
})->with([
    'status changed' => [fn ($rule) => $rule->update(['status' => ComplianceStatus::NotAllowed])],
    'rule removed' => [fn ($rule) => $rule->delete()],
]);

it('keeps the priority when a later ordinary trigger merges into the row', function () {
    $catalog = CatalogScenario::create();
    $product = $catalog->product();
    clearOutbox();
    Queue::fake([ProcessSearchOutbox::class]);

    $catalog->compliance($product, ComplianceStatus::NotAllowed);
    event(new OfferPublished(1, 1, $product->id, 1, 'created', null));

    expect(outboxEntries())->toBe(["product:{$product->id}!"]);
});

it('ignores a compliance rule saved without changes', function () {
    $catalog = CatalogScenario::create();
    $rule = $catalog->allow($catalog->product());
    clearOutbox();
    Queue::fake([ProcessSearchOutbox::class]);

    $rule->fresh()->save();

    expect(outboxEntries())->toBe([]);
    Queue::assertNotPushed(ProcessSearchOutbox::class);
});

/**
 * A merchant with active offers on two products, an inactive offer on a
 * third, and another merchant's offer on a fourth.
 *
 * @return array{catalog: CatalogScenario, merchant: Merchant, expected: list<string>}
 */
function merchantWithOffers(): array
{
    $catalog = CatalogScenario::create();
    $merchant = $catalog->merchant(['DE' => 390]);
    $listed = [$catalog->product(), $catalog->product()];

    foreach ($listed as $product) {
        $catalog->offer($product, $merchant, 2500);
    }

    $catalog->offer($catalog->product(), $merchant, 2500, ['is_active' => false]);
    $catalog->offer($catalog->product(), $catalog->merchant(), 2500);
    clearOutbox();

    return [
        'catalog' => $catalog,
        'merchant' => $merchant,
        'expected' => ["merchant:{$merchant->id}", "product:{$listed[0]->id}", "product:{$listed[1]->id}"],
    ];
}

it('queues the merchant document and its offered products when a merchant input changes', function (Closure $change) {
    ['catalog' => $catalog, 'merchant' => $merchant, 'expected' => $expected] = merchantWithOffers();

    $change($catalog, $merchant);

    expect(outboxEntries())->toBe($expected);
})->with([
    'coupon created' => [fn (CatalogScenario $catalog, Merchant $merchant) => $catalog->coupon($merchant, Coupon::factory())],
    'coupon removed' => [function (CatalogScenario $catalog, Merchant $merchant) {
        $coupon = Coupon::factory()->create(['merchant_id' => $merchant->id]);
        clearOutbox();
        $coupon->delete();
    }],
    'shipping zone changed' => [fn (CatalogScenario $catalog, Merchant $merchant) => MerchantShippingZone::query()->where('merchant_id', $merchant->id)->first()->update(['cost_minor' => 490])],
    'trust signal recorded' => [fn (CatalogScenario $catalog, Merchant $merchant) => MerchantTrustSignal::factory()->create(['merchant_id' => $merchant->id])],
    'risk event resolved' => [function (CatalogScenario $catalog, Merchant $merchant) {
        $event = MerchantRiskEvent::query()->create(['merchant_id' => $merchant->id, 'kind' => 'rating_spike', 'severity' => 'CRITICAL', 'detected_at' => now()]);
        clearOutbox();
        $event->update(['resolved_at' => now()]);
    }],
    'merchant suspended' => [fn (CatalogScenario $catalog, Merchant $merchant) => $merchant->update(['status' => 'suspended'])],
]);

it('ignores a merchant saved without changes', function () {
    ['merchant' => $merchant] = merchantWithOffers();

    $merchant->fresh()->save();

    expect(outboxEntries())->toBe([]);
});

it('reads the merchant fan-out in bounded, id-ordered chunks', function () {
    config(['comparo.search.indexing.fan_out_chunk' => 2]);
    $catalog = CatalogScenario::create();
    $merchant = $catalog->merchant();
    $products = array_map(fn () => $catalog->product(), range(1, 5));

    foreach ($products as $product) {
        $catalog->offer($product, $merchant, 2500);
    }

    clearOutbox();
    $offerReads = [];
    DB::listen(function ($query) use (&$offerReads) {
        if (str_contains($query->sql, 'from "offers"')) {
            $offerReads[] = $query->sql;
        }
    });

    $merchant->update(['name' => 'Renamed shop']);

    expect($offerReads)->toHaveCount(3)
        ->and($offerReads)->each->toContain('limit 2')
        ->and(outboxEntries())->toBe(["merchant:{$merchant->id}", ...array_map(static fn (Product $product): string => "product:{$product->id}", $products)]);
});

it('queues a changed product, and its brand, category and ingredients when its listing status changes', function () {
    $product = Product::factory()->create();
    $ingredient = Ingredient::query()->create(['slug' => 'creatine', 'name' => 'Creatine']);
    $product->ingredients()->attach($ingredient->id, ['position' => 0, 'is_listed' => true, 'is_carrier' => false]);
    clearOutbox();

    $product->update(['name' => 'Renamed whey']);
    expect(outboxEntries())->toBe(["product:{$product->id}"]);

    clearOutbox();
    $product->update(['status' => 'retired']);
    expect(outboxEntries())->toBe([
        "brand:{$product->brand_id}",
        "category:{$product->category_id}",
        "ingredient:{$ingredient->id}",
        "product:{$product->id}",
    ]);
});

it('queues the previous and the new brand when a product moves', function () {
    $product = Product::factory()->create();
    $previousBrandId = $product->brand_id;
    $brand = Brand::factory()->create();
    clearOutbox();

    $product->update(['brand_id' => $brand->id]);

    expect(outboxEntries())->toContain("brand:{$previousBrandId}", "brand:{$brand->id}", "product:{$product->id}");
});

it('queues a brand and, when renamed, its products', function () {
    $brand = Brand::factory()->create();
    $products = Product::factory()->count(2)->create(['brand_id' => $brand->id]);
    $other = Product::factory()->create();
    clearOutbox();

    $brand->update(['description' => 'Updated story']);
    expect(outboxEntries())->toBe(["brand:{$brand->id}"]);

    clearOutbox();
    $brand->update(['name' => 'Renamed brand']);
    expect(outboxEntries())->toBe(["brand:{$brand->id}", "product:{$products[0]->id}", "product:{$products[1]->id}"])
        ->and(outboxEntries())->not->toContain("product:{$other->id}");
});

it('queues the brand and its products when a brand alias changes', function () {
    $product = Product::factory()->create();
    clearOutbox();

    BrandAlias::factory()->create(['brand_id' => $product->brand_id]);

    expect(outboxEntries())->toBe(["brand:{$product->brand_id}", "product:{$product->id}"]);
});

it('queues a renamed category with its descendants and all their products', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $root->id]);
    $grandchild = Category::factory()->create(['parent_id' => $child->id]);
    $unrelated = Category::factory()->create();
    $inChild = Product::factory()->create(['category_id' => $child->id]);
    $inGrandchild = Product::factory()->create(['category_id' => $grandchild->id]);
    Product::factory()->create(['category_id' => $unrelated->id]);
    clearOutbox();

    $child->update(['slug' => 'renamed-child']);

    expect(outboxEntries())->toBe([
        "category:{$child->id}",
        "category:{$grandchild->id}",
        "product:{$inChild->id}",
        "product:{$inGrandchild->id}",
    ]);
});

it('queues a renamed ingredient and the products listing it', function () {
    $ingredient = Ingredient::query()->create(['slug' => 'creatine', 'name' => 'Creatine']);
    $product = Product::factory()->create();
    $product->ingredients()->attach($ingredient->id, ['position' => 0, 'is_listed' => true, 'is_carrier' => false]);
    clearOutbox();

    $ingredient->update(['name' => 'Creatine monohydrate']);

    expect(outboxEntries())->toBe(["ingredient:{$ingredient->id}", "product:{$product->id}"]);
});

it('queues the document of a deleted brand, category or ingredient', function (Closure $make, string $entity) {
    $model = $make();
    clearOutbox();

    $model->delete();

    expect(outboxEntries())->toBe(["{$entity}:{$model->id}"]);
})->with([
    'brand' => [fn () => Brand::factory()->create(), 'brand'],
    'category' => [fn () => Category::factory()->create(), 'category'],
    'ingredient' => [fn () => Ingredient::query()->create(['slug' => 'zinc', 'name' => 'Zinc']), 'ingredient'],
]);

it('writes outbox rows only after the transaction commits, and none on rollback', function () {
    $product = Product::factory()->create();
    clearOutbox();
    $insideTransaction = null;

    DB::transaction(function () use ($product, &$insideTransaction) {
        $product->update(['name' => 'Committed name']);
        $insideTransaction = outboxEntries();
    });

    try {
        DB::transaction(function () use ($product) {
            Brand::query()->whereKey($product->brand_id)->first()->update(['name' => 'Rolled back']);

            throw new RuntimeException('rolled back');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($insideTransaction)->toBe([])
        ->and(outboxEntries())->toBe(["product:{$product->id}"]);
});

it('requests a full reindex when a market is activated or deactivated', function () {
    $country = Country::factory()->code('AT')->inactive()->create();
    Queue::fake([QueueFullSearchReindex::class]);

    Country::factory()->code('PL')->create();
    Queue::assertNotPushed(QueueFullSearchReindex::class);

    $country->update(['name' => 'Österreich']);
    Queue::assertNotPushed(QueueFullSearchReindex::class);

    $country->update(['is_active' => true]);
    Queue::assertPushed(QueueFullSearchReindex::class, fn (QueueFullSearchReindex $job): bool => $job->queue === 'search');
});

it('queues the product of a directly written offer, and the previous product when it moves', function () {
    $catalog = CatalogScenario::create();
    $merchant = $catalog->merchant();
    $product = $catalog->product();
    $other = $catalog->product();
    clearOutbox();

    $offer = $catalog->offer($product, $merchant, 2500);
    expect(outboxEntries())->toBe(["product:{$product->id}"]);

    clearOutbox();
    $offer->update(['product_id' => $other->id]);
    expect(outboxEntries())->toBe(["product:{$product->id}", "product:{$other->id}"]);
});
